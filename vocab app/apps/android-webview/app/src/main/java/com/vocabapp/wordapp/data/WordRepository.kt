package com.vocabapp.wordapp.data

import androidx.room.withTransaction
import com.google.gson.Gson
import com.google.gson.annotations.SerializedName
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.withContext
import okhttp3.OkHttpClient
import okhttp3.Request
import java.time.LocalDate

class WordRepository(
    private val database: AppDatabase,
    private val client: OkHttpClient = OkHttpClient(),
    private val gson: Gson = Gson()
) {
    private val bankDao = database.wordBankDao()
    private val wordDao = database.wordDao()
    private val reviewDao = database.reviewDao()
    private val answerDao = database.answerDao()

    fun observeBanks(): Flow<List<WordBankEntity>> = bankDao.observeBanks()

    fun observeWords(bankCode: String, query: String = ""): Flow<List<WordEntity>> =
        if (query.isBlank()) wordDao.observeWords(bankCode) else wordDao.searchWords(bankCode, query)

    suspend fun activeBank(): WordBankEntity? = bankDao.activeBank()

    suspend fun setActiveBank(code: String) = bankDao.setActive(code)

    suspend fun fetchCloudBanks(apiBaseUrl: String): List<CloudWordBank> = withContext(Dispatchers.IO) {
        val payload = getJson(apiBaseUrl.trimEnd('/') + "/word-banks.php")
        val response = gson.fromJson(payload, WordBanksResponse::class.java)
        response.banks.map {
            CloudWordBank(
                code = it.code,
                title = it.title,
                description = it.description.orEmpty(),
                wordCount = it.wordCount ?: 0
            )
        }
    }

    suspend fun downloadBank(apiBaseUrl: String, bank: CloudWordBank): Int = withContext(Dispatchers.IO) {
        val payload = getJson(apiBaseUrl.trimEnd('/') + "/word-bank.php?code=" + bank.code)
        val response = gson.fromJson(payload, WordBankDownloadResponse::class.java)
        val now = System.currentTimeMillis()
        val words = response.words.map {
            WordEntity(
                bankCode = response.bank.code,
                word = it.word.trim().lowercase(),
                partOfSpeech = it.partOfSpeech.orEmpty(),
                meaningZh = it.meaningZh.orEmpty(),
                exampleEn = it.exampleEn.orEmpty(),
                source = "cloud:${response.bank.code}",
                difficulty = (it.difficulty ?: 1).coerceIn(1, 5),
                createdAt = now
            )
        }.filter { it.word.isNotBlank() && it.meaningZh.isNotBlank() }

        database.withTransaction {
            wordDao.deleteByBank(response.bank.code)
            bankDao.upsert(
                WordBankEntity(
                    code = response.bank.code,
                    title = response.bank.title,
                    description = response.bank.description.orEmpty(),
                    wordCount = words.size,
                    downloadedAt = now,
                    active = true
                )
            )
            wordDao.upsertAll(words)
            bankDao.setActive(response.bank.code)
        }
        words.size
    }

    suspend fun todayPlan(bankCode: String, newWordsPerDay: Int, reviewLimit: Int, today: LocalDate = LocalDate.now()): StudyPlan {
        val studyWords = reviewDao.allWordsForPlanningRaw(bankCode).map { it.toStudyWord(today.toString()) }
        return StudyPlanner(newWordsPerDay, reviewLimit).plan(studyWords)
    }

    suspend fun addLocalWord(
        activeBankCode: String?,
        word: String,
        partOfSpeech: String,
        meaningZh: String,
        exampleEn: String
    ): Boolean = withContext(Dispatchers.IO) {
        val cleanWord = word.trim().lowercase()
        val cleanMeaning = meaningZh.trim()
        if (cleanWord.isBlank() || cleanMeaning.isBlank()) return@withContext false

        val bankCode = activeBankCode?.takeIf { it.isNotBlank() } ?: "personal"
        database.withTransaction {
            if (activeBankCode.isNullOrBlank()) {
                bankDao.upsert(
                    WordBankEntity(
                        code = bankCode,
                        title = "Personal Words",
                        description = "Words added on this phone.",
                        wordCount = 0,
                        downloadedAt = System.currentTimeMillis(),
                        active = true
                    )
                )
                bankDao.setActive(bankCode)
            }
            wordDao.upsert(
                WordEntity(
                    bankCode = bankCode,
                    word = cleanWord,
                    partOfSpeech = partOfSpeech.trim(),
                    meaningZh = cleanMeaning,
                    exampleEn = exampleEn.trim(),
                    source = "local_user"
                )
            )
        }
        true
    }

    suspend fun answer(studyWord: StudyWord, chosenMeaning: String, today: LocalDate = LocalDate.now()): Boolean {
        val remembered = normalize(chosenMeaning) == normalize(studyWord.word.meaningZh)
        val currentReview = reviewDao.findByWordId(studyWord.word.id)
        val todayText = today.toString()
        val daily = answerDao.findDaily(todayText, studyWord.word.id)
        val nextMastery = nextMastery(studyWord.word.masteryScore, remembered)

        database.withTransaction {
            answerDao.insertAttempt(
                AnswerAttemptEntity(
                    studyDate = todayText,
                    wordId = studyWord.word.id,
                    chosenMeaningZh = chosenMeaning,
                    remembered = remembered,
                    isDueReview = studyWord.isDueReview
                )
            )
            answerDao.upsertDaily(
                (daily ?: DailyAnswerEntity(studyDate = todayText, wordId = studyWord.word.id, remembered = remembered, everWrong = !remembered))
                    .copy(
                        remembered = remembered,
                        everWrong = daily?.everWrong == true || !remembered,
                        attemptCount = (daily?.attemptCount ?: 0) + 1,
                        isDueReview = studyWord.isDueReview,
                        answeredAt = System.currentTimeMillis()
                    )
            )
            wordDao.update(studyWord.word.copy(masteryScore = nextMastery))
            reviewDao.upsert(ReviewScheduler.nextAfterAnswer(studyWord.word.id, currentReview, remembered, today))
        }
        return remembered
    }

    suspend fun deleteWord(id: Long) = wordDao.delete(id)

    private fun getJson(url: String): String {
        val request = Request.Builder().url(url).build()
        client.newCall(request).execute().use { response ->
            if (!response.isSuccessful) error("HTTP ${response.code}")
            return response.body?.string() ?: error("Empty response")
        }
    }

    private fun normalize(value: String): String = value.trim().replace(Regex("\\s+"), "")

    private fun nextMastery(current: Int, remembered: Boolean): Int =
        if (remembered) (current + 20).coerceAtMost(100) else (current - 25).coerceAtLeast(0)
}

private data class WordBanksResponse(val banks: List<ApiBank> = emptyList())

private data class WordBankDownloadResponse(
    val bank: ApiBank,
    val words: List<ApiWord> = emptyList()
)

private data class ApiBank(
    val code: String,
    val title: String,
    val description: String? = "",
    @SerializedName("word_count") val wordCount: Int? = 0
)

private data class ApiWord(
    val word: String,
    @SerializedName("part_of_speech") val partOfSpeech: String? = "",
    @SerializedName("meaning_zh") val meaningZh: String? = "",
    @SerializedName("example_en") val exampleEn: String? = "",
    val difficulty: Int? = 1
)
