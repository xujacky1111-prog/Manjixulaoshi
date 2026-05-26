package com.vocabapp.wordapp.data

data class ParsedWord(
    val word: String,
    val partOfSpeech: String,
    val meaningZh: String,
    val exampleEn: String = ""
)

object TextWordParser {
    private val bulletPrefix = Regex("^[-*\\d.\\s]+")
    private val posPattern = Regex("\\b(n|v|adj|adv|prep|conj|pron|noun|verb|adjective|adverb)\\.?\\b", RegexOption.IGNORE_CASE)

    fun parse(input: String): List<ParsedWord> =
        input.lines()
            .mapNotNull { parseLine(it) }
            .distinctBy { it.word.lowercase() }

    private fun parseLine(rawLine: String): ParsedWord? {
        val line = rawLine.trim().removeSurrounding("|").trim()
        if (line.isBlank() || line.startsWith("#") || line.contains("---")) return null

        val cleaned = bulletPrefix.replace(line, "").trim()
        val cells = splitCells(cleaned)
        if (cells.isEmpty()) return null

        val word = cells.first().trim('`', '*', ' ', '-', ',', ';', ':')
        if (!word.matches(Regex("[A-Za-z][A-Za-z\\-']{1,40}"))) return null
        if (word.equals("word", ignoreCase = true) || word.equals("english", ignoreCase = true)) return null

        if (cells.size >= 3) {
            return ParsedWord(
                word = word.lowercase(),
                partOfSpeech = cells.getOrNull(1)?.trim().orEmpty(),
                meaningZh = cells.getOrNull(2)?.trim().orEmpty(),
                exampleEn = cells.getOrNull(3)?.trim().orEmpty()
            ).takeIf { it.meaningZh.isNotBlank() }
        }

        val rest = cells.drop(1).joinToString(" ").ifBlank {
            cleaned.removePrefix(word).trim(' ', '-', ':', ';')
        }
        if (rest.isBlank()) return null

        val pos = posPattern.find(rest)?.value?.trim('.').orEmpty()
        val meaning = rest.replace(posPattern, "").trim(' ', '-', ':', ';').ifBlank { rest }

        return ParsedWord(word.lowercase(), pos, meaning)
    }

    private fun splitCells(line: String): List<String> =
        when {
            line.contains("|") -> line.split("|")
            line.contains("\t") -> line.split("\t")
            line.contains(",") -> line.split(",")
            else -> line.split(Regex("\\s{2,}"))
        }.map { it.trim() }.filter { it.isNotBlank() }
}
