package com.vocabapp.wordapp.data

import java.time.LocalDate

data class StudyPlan(
    val dueReviews: List<StudyWord>,
    val newWords: List<StudyWord>
) {
    val all: List<StudyWord> = dueReviews + newWords
}

class StudyPlanner(
    private val newWordsPerDay: Int = 80,
    private val reviewLimit: Int = 80
) {
    fun plan(words: List<StudyWord>): StudyPlan {
        val due = words
            .filter { it.isDueReview }
            .sortedWith(compareByDescending<StudyWord> { it.review?.wrongStreak ?: 0 }.thenBy { it.review?.nextReviewDate })
            .take(reviewLimit)

        val dueIds = due.map { it.word.id }.toSet()
        val fresh = words
            .filter { it.word.id !in dueIds && (it.review == null || it.review.isNew) }
            .sortedWith(compareBy<StudyWord> { it.word.difficulty }.thenBy { it.word.createdAt })
            .take(newWordsPerDay)

        return StudyPlan(due, fresh)
    }
}

object ReviewScheduler {
    private val intervals = listOf(1, 3, 7, 14, 30)

    fun nextAfterAnswer(
        wordId: Long,
        existing: ReviewEntity?,
        remembered: Boolean,
        today: LocalDate = LocalDate.now()
    ): ReviewEntity {
        if (!remembered) {
            return (existing ?: ReviewEntity(wordId = wordId, nextReviewDate = today.toString()))
                .copy(
                    wordId = wordId,
                    nextReviewDate = today.toString(),
                    intervalDays = 0,
                    correctStreak = 0,
                    wrongStreak = (existing?.wrongStreak ?: 0) + 1,
                    lastReviewedAt = System.currentTimeMillis(),
                    isNew = false
                )
        }

        val currentInterval = existing?.intervalDays ?: 0
        val nextInterval = intervals.firstOrNull { it > currentInterval } ?: intervals.last()
        return (existing ?: ReviewEntity(wordId = wordId, nextReviewDate = today.plusDays(nextInterval.toLong()).toString()))
            .copy(
                wordId = wordId,
                nextReviewDate = today.plusDays(nextInterval.toLong()).toString(),
                intervalDays = nextInterval,
                correctStreak = (existing?.correctStreak ?: 0) + 1,
                wrongStreak = 0,
                lastReviewedAt = System.currentTimeMillis(),
                isNew = false
            )
    }
}
