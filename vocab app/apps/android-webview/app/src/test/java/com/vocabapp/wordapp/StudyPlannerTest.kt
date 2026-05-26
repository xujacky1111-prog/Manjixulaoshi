package com.vocabapp.wordapp

import com.vocabapp.wordapp.data.ReviewEntity
import com.vocabapp.wordapp.data.ReviewScheduler
import com.vocabapp.wordapp.data.StudyPlanner
import com.vocabapp.wordapp.data.StudyWord
import com.vocabapp.wordapp.data.WordEntity
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Test
import java.time.LocalDate

class StudyPlannerTest {
    @Test
    fun dueReviewsComeBeforeNewWords() {
        val due = studyWord(1, "review", ReviewEntity(wordId = 1, nextReviewDate = "2026-05-15", isNew = false), true)
        val fresh = studyWord(2, "fresh", null, false)

        val plan = StudyPlanner(newWordsPerDay = 20, reviewLimit = 80).plan(listOf(fresh, due))

        assertEquals(listOf(due, fresh), plan.all)
    }

    @Test
    fun correctAnswerAdvancesInterval() {
        val next = ReviewScheduler.nextAfterAnswer(
            wordId = 1,
            existing = ReviewEntity(wordId = 1, nextReviewDate = "2026-05-16", intervalDays = 3, isNew = false),
            remembered = true,
            today = LocalDate.parse("2026-05-16")
        )

        assertEquals(7, next.intervalDays)
        assertEquals("2026-05-23", next.nextReviewDate)
        assertFalse(next.isNew)
    }

    private fun studyWord(id: Long, word: String, review: ReviewEntity?, due: Boolean): StudyWord =
        StudyWord(WordEntity(id = id, bankCode = "high_school", word = word, meaningZh = word), review, due)
}
