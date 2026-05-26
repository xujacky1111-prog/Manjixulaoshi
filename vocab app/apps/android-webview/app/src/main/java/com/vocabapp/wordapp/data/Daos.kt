package com.vocabapp.wordapp.data

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import androidx.room.Transaction
import androidx.room.Update
import kotlinx.coroutines.flow.Flow

@Dao
interface WordBankDao {
    @Query("SELECT * FROM word_banks ORDER BY active DESC, title ASC")
    fun observeBanks(): Flow<List<WordBankEntity>>

    @Query("SELECT * FROM word_banks WHERE active = 1 LIMIT 1")
    suspend fun activeBank(): WordBankEntity?

    @Query("UPDATE word_banks SET active = CASE WHEN code = :code THEN 1 ELSE 0 END")
    suspend fun setActive(code: String)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsert(bank: WordBankEntity)
}

@Dao
interface WordDao {
    @Query("SELECT * FROM words WHERE bankCode = :bankCode ORDER BY createdAt DESC")
    fun observeWords(bankCode: String): Flow<List<WordEntity>>

    @Query(
        "SELECT * FROM words WHERE bankCode = :bankCode AND (word LIKE '%' || :query || '%' OR meaningZh LIKE '%' || :query || '%') ORDER BY createdAt DESC"
    )
    fun searchWords(bankCode: String, query: String): Flow<List<WordEntity>>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsertAll(words: List<WordEntity>)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsert(word: WordEntity): Long

    @Update
    suspend fun update(word: WordEntity)

    @Query("DELETE FROM words WHERE bankCode = :bankCode")
    suspend fun deleteByBank(bankCode: String)

    @Query("DELETE FROM words WHERE id = :id")
    suspend fun delete(id: Long)
}

@Dao
interface ReviewDao {
    @Query(
        """
        SELECT words.*, reviews.id AS review_id, reviews.wordId AS review_wordId, reviews.nextReviewDate AS review_nextReviewDate,
        reviews.intervalDays AS review_intervalDays, reviews.correctStreak AS review_correctStreak, reviews.wrongStreak AS review_wrongStreak,
        reviews.lastReviewedAt AS review_lastReviewedAt, reviews.isNew AS review_isNew
        FROM words
        LEFT JOIN reviews ON reviews.wordId = words.id
        WHERE words.bankCode = :bankCode
        ORDER BY words.createdAt ASC
        """
    )
    suspend fun allWordsForPlanningRaw(bankCode: String): List<WordWithReviewRow>

    @Query("SELECT * FROM reviews WHERE wordId = :wordId LIMIT 1")
    suspend fun findByWordId(wordId: Long): ReviewEntity?

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsert(review: ReviewEntity)
}

@Dao
interface AnswerDao {
    @Query("SELECT COUNT(*) FROM daily_answers WHERE studyDate = :date AND remembered = 1")
    suspend fun completedToday(date: String): Int

    @Query("SELECT * FROM daily_answers WHERE studyDate = :date AND wordId = :wordId LIMIT 1")
    suspend fun findDaily(date: String, wordId: Long): DailyAnswerEntity?

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsertDaily(answer: DailyAnswerEntity)

    @Insert
    suspend fun insertAttempt(attempt: AnswerAttemptEntity)
}

data class WordWithReviewRow(
    val id: Long,
    val bankCode: String,
    val word: String,
    val partOfSpeech: String,
    val meaningZh: String,
    val exampleEn: String,
    val source: String,
    val difficulty: Int,
    val masteryScore: Int,
    val createdAt: Long,
    val review_id: Long?,
    val review_wordId: Long?,
    val review_nextReviewDate: String?,
    val review_intervalDays: Int?,
    val review_correctStreak: Int?,
    val review_wrongStreak: Int?,
    val review_lastReviewedAt: Long?,
    val review_isNew: Boolean?
) {
    fun toStudyWord(today: String): StudyWord {
        val wordEntity = WordEntity(id, bankCode, word, partOfSpeech, meaningZh, exampleEn, source, difficulty, masteryScore, createdAt)
        val review = if (review_id == null || review_wordId == null || review_nextReviewDate == null) {
            null
        } else {
            ReviewEntity(
                id = review_id,
                wordId = review_wordId,
                nextReviewDate = review_nextReviewDate,
                intervalDays = review_intervalDays ?: 0,
                correctStreak = review_correctStreak ?: 0,
                wrongStreak = review_wrongStreak ?: 0,
                lastReviewedAt = review_lastReviewedAt,
                isNew = review_isNew ?: true
            )
        }
        return StudyWord(wordEntity, review, review != null && !review.isNew && review.nextReviewDate <= today)
    }
}
