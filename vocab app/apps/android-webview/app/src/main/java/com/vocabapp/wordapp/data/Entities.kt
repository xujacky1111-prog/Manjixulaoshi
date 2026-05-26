package com.vocabapp.wordapp.data

import androidx.room.Entity
import androidx.room.ForeignKey
import androidx.room.Index
import androidx.room.PrimaryKey

@Entity(tableName = "word_banks")
data class WordBankEntity(
    @PrimaryKey val code: String,
    val title: String,
    val description: String = "",
    val wordCount: Int = 0,
    val downloadedAt: Long? = null,
    val active: Boolean = false
)

@Entity(
    tableName = "words",
    foreignKeys = [
        ForeignKey(
            entity = WordBankEntity::class,
            parentColumns = ["code"],
            childColumns = ["bankCode"],
            onDelete = ForeignKey.CASCADE
        )
    ],
    indices = [Index(value = ["bankCode", "word"], unique = true), Index(value = ["bankCode"])]
)
data class WordEntity(
    @PrimaryKey(autoGenerate = true) val id: Long = 0,
    val bankCode: String,
    val word: String,
    val partOfSpeech: String = "",
    val meaningZh: String,
    val exampleEn: String = "",
    val source: String = "cloud_bank",
    val difficulty: Int = 1,
    val masteryScore: Int = 0,
    val createdAt: Long = System.currentTimeMillis()
)

@Entity(
    tableName = "reviews",
    foreignKeys = [
        ForeignKey(
            entity = WordEntity::class,
            parentColumns = ["id"],
            childColumns = ["wordId"],
            onDelete = ForeignKey.CASCADE
        )
    ],
    indices = [Index(value = ["wordId"], unique = true), Index(value = ["nextReviewDate"])]
)
data class ReviewEntity(
    @PrimaryKey(autoGenerate = true) val id: Long = 0,
    val wordId: Long,
    val nextReviewDate: String,
    val intervalDays: Int = 0,
    val correctStreak: Int = 0,
    val wrongStreak: Int = 0,
    val lastReviewedAt: Long? = null,
    val isNew: Boolean = true
)

@Entity(
    tableName = "daily_answers",
    foreignKeys = [
        ForeignKey(
            entity = WordEntity::class,
            parentColumns = ["id"],
            childColumns = ["wordId"],
            onDelete = ForeignKey.CASCADE
        )
    ],
    indices = [Index(value = ["studyDate", "wordId"], unique = true), Index(value = ["wordId"])]
)
data class DailyAnswerEntity(
    @PrimaryKey(autoGenerate = true) val id: Long = 0,
    val studyDate: String,
    val wordId: Long,
    val remembered: Boolean,
    val everWrong: Boolean,
    val attemptCount: Int = 1,
    val isDueReview: Boolean = false,
    val answeredAt: Long = System.currentTimeMillis()
)

@Entity(
    tableName = "answer_attempts",
    foreignKeys = [
        ForeignKey(
            entity = WordEntity::class,
            parentColumns = ["id"],
            childColumns = ["wordId"],
            onDelete = ForeignKey.CASCADE
        )
    ],
    indices = [Index(value = ["studyDate"]), Index(value = ["wordId", "studyDate"])]
)
data class AnswerAttemptEntity(
    @PrimaryKey(autoGenerate = true) val id: Long = 0,
    val studyDate: String,
    val wordId: Long,
    val chosenMeaningZh: String,
    val remembered: Boolean,
    val isDueReview: Boolean = false,
    val answeredAt: Long = System.currentTimeMillis()
)

data class StudyWord(
    val word: WordEntity,
    val review: ReviewEntity?,
    val isDueReview: Boolean
)

data class CloudWordBank(
    val code: String,
    val title: String,
    val description: String = "",
    val wordCount: Int = 0
)

data class CloudWord(
    val word: String,
    val partOfSpeech: String = "",
    val meaningZh: String,
    val exampleEn: String = "",
    val difficulty: Int = 1
)
