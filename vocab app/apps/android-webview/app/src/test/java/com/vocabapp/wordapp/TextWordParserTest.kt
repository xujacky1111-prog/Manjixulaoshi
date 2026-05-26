package com.vocabapp.wordapp

import com.vocabapp.wordapp.data.TextWordParser
import org.junit.Assert.assertEquals
import org.junit.Test

class TextWordParserTest {
    @Test
    fun parsesMarkdownTableAndSkipsHeader() {
        val input = """
            | word | pos | meaning | example |
            |---|---|---|---|
            | fluent | adj | fluent meaning | She is fluent in English. |
            | review | v | review meaning | Review the words. |
        """.trimIndent()

        val words = TextWordParser.parse(input)

        assertEquals(2, words.size)
        assertEquals("fluent", words[0].word)
        assertEquals("adj", words[0].partOfSpeech)
        assertEquals("fluent meaning", words[0].meaningZh)
        assertEquals("She is fluent in English.", words[0].exampleEn)
    }
}
