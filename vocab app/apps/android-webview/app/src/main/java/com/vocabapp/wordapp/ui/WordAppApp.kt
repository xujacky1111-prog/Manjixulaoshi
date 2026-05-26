package com.vocabapp.wordapp.ui

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CloudDownload
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.List
import androidx.compose.material.icons.filled.Settings
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.vocabapp.wordapp.data.CloudWordBank
import com.vocabapp.wordapp.data.StudyWord
import com.vocabapp.wordapp.data.WordBankEntity
import com.vocabapp.wordapp.data.WordEntity
import com.vocabapp.wordapp.viewmodel.AppViewModel

private enum class Tab {
    Today,
    Banks,
    Library,
    Settings
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun WordAppApp(viewModel: AppViewModel) {
    var tab by remember { mutableStateOf(Tab.Today) }
    val activeBank by viewModel.activeBank.collectAsState()

    MaterialTheme(
        colorScheme = MaterialTheme.colorScheme.copy(
            primary = Color(0xFF2F6F5E),
            secondary = Color(0xFF6D5B98),
            tertiary = Color(0xFFC47A3B)
        )
    ) {
        Surface(modifier = Modifier.fillMaxSize()) {
            Scaffold(
                topBar = { TopAppBar(title = { Text(activeBank?.title ?: "WordApp") }) },
                bottomBar = {
                    NavigationBar {
                        NavigationBarItem(selected = tab == Tab.Today, onClick = { tab = Tab.Today }, icon = { Icon(Icons.Default.Home, null) }, label = { Text("Today") })
                        NavigationBarItem(selected = tab == Tab.Banks, onClick = { tab = Tab.Banks }, icon = { Icon(Icons.Default.CloudDownload, null) }, label = { Text("Banks") })
                        NavigationBarItem(selected = tab == Tab.Library, onClick = { tab = Tab.Library }, icon = { Icon(Icons.Default.List, null) }, label = { Text("Words") })
                        NavigationBarItem(selected = tab == Tab.Settings, onClick = { tab = Tab.Settings }, icon = { Icon(Icons.Default.Settings, null) }, label = { Text("Settings") })
                    }
                }
            ) { padding ->
                Column(Modifier.padding(padding).fillMaxSize()) {
                    when (tab) {
                        Tab.Today -> TodayScreen(viewModel, onOpenBanks = { tab = Tab.Banks })
                        Tab.Banks -> BanksScreen(viewModel)
                        Tab.Library -> LibraryScreen(viewModel)
                        Tab.Settings -> SettingsScreen(viewModel)
                    }
                }
            }
        }
    }
}

@Composable
private fun TodayScreen(viewModel: AppViewModel, onOpenBanks: () -> Unit) {
    val activeBank by viewModel.activeBank.collectAsState()
    val plan by viewModel.studyPlan.collectAsState()
    val words by viewModel.words.collectAsState()
    val answerMessage by viewModel.answerMessage.collectAsState()
    var index by remember(plan.all) { mutableIntStateOf(0) }
    val current = plan.all.getOrNull(index)

    LazyColumn(
        modifier = Modifier.fillMaxSize().padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        item {
            Text("Today", style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold)
            Text(activeBank?.title ?: "No local word bank yet.", color = Color(0xFF60706A))
        }

        if (activeBank == null) {
            item {
                EmptyState("Download a cloud word bank first. Words and study progress will stay on this phone.")
                Button(onClick = onOpenBanks, modifier = Modifier.fillMaxWidth().padding(top = 8.dp)) {
                    Text("Download a word bank")
                }
            }
        } else if (current == null) {
            item { EmptyState("No words are due today. Check the Banks tab if you have not downloaded a full bank yet.") }
        } else {
            item {
                Text("Reviews ${plan.dueReviews.size}, new words ${plan.newWords.size}")
                StudyCard(current)
                Spacer(Modifier.height(12.dp))
                current.word.options(words).forEach { option ->
                    Button(
                        onClick = {
                            viewModel.answer(current, option)
                            index = (index + 1).coerceAtMost(plan.all.size - 1)
                        },
                        modifier = Modifier.fillMaxWidth().padding(bottom = 8.dp)
                    ) {
                        Text(option)
                    }
                }
                if (answerMessage.isNotBlank()) Text(answerMessage, color = MaterialTheme.colorScheme.primary)
            }
        }
    }
}

@Composable
private fun StudyCard(studyWord: StudyWord) {
    Card(colors = CardDefaults.cardColors(containerColor = Color(0xFFF4F7F3))) {
        Column(Modifier.padding(18.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(studyWord.word.word, style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold)
                if (studyWord.word.partOfSpeech.isNotBlank()) Text(studyWord.word.partOfSpeech, color = Color(0xFF6D5B98))
            }
            if (studyWord.word.exampleEn.isNotBlank()) Text(studyWord.word.exampleEn, color = Color(0xFF4C5853))
            Text(if (studyWord.isDueReview) "Review" else "New word", color = Color(0xFFC47A3B))
        }
    }
}

@Composable
private fun BanksScreen(viewModel: AppViewModel) {
    val localBanks by viewModel.localBanks.collectAsState()
    val download by viewModel.download.collectAsState()

    LazyColumn(Modifier.fillMaxSize().padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        item {
            Text("Cloud Banks", style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold)
            Text("Downloaded banks are stored locally. Progress is not uploaded.", color = Color(0xFF60706A))
            if (download.loading) LinearProgressIndicator(Modifier.fillMaxWidth().padding(top = 8.dp))
            if (download.message.isNotBlank()) Text(download.message, color = MaterialTheme.colorScheme.primary)
            OutlinedButton(onClick = { viewModel.refreshCloudBanks() }, modifier = Modifier.fillMaxWidth().padding(top = 8.dp)) {
                Text("Refresh")
            }
        }
        items(download.cloudBanks, key = { it.code }) { bank -> CloudBankRow(bank) { viewModel.downloadBank(bank) } }
        item {
            Text("On This Phone", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
        }
        if (localBanks.isEmpty()) {
            item { EmptyState("No downloaded word banks yet.") }
        } else {
            items(localBanks, key = { it.code }) { bank -> LocalBankRow(bank) { viewModel.setActiveBank(bank.code) } }
        }
    }
}

@Composable
private fun CloudBankRow(bank: CloudWordBank, onDownload: () -> Unit) {
    Card {
        Column(Modifier.fillMaxWidth().padding(14.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Text(bank.title, fontWeight = FontWeight.Bold)
            Text("${bank.wordCount} words", color = Color(0xFF60706A))
            if (bank.description.isNotBlank()) Text(bank.description)
            Button(onClick = onDownload, modifier = Modifier.fillMaxWidth()) { Text("Download") }
        }
    }
}

@Composable
private fun LocalBankRow(bank: WordBankEntity, onActivate: () -> Unit) {
    Card(colors = CardDefaults.cardColors(containerColor = if (bank.active) Color(0xFFE5F2ED) else Color.White)) {
        Column(Modifier.fillMaxWidth().padding(14.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Text(bank.title, fontWeight = FontWeight.Bold)
            Text("${bank.wordCount} words" + if (bank.active) " - active" else "", color = Color(0xFF60706A))
            OutlinedButton(onClick = onActivate, modifier = Modifier.fillMaxWidth()) { Text("Use this bank") }
        }
    }
}

@Composable
private fun LibraryScreen(viewModel: AppViewModel) {
    val words by viewModel.words.collectAsState()
    val activeBank by viewModel.activeBank.collectAsState()
    val wordEditMessage by viewModel.wordEditMessage.collectAsState()
    var query by remember { mutableStateOf("") }
    var word by remember { mutableStateOf("") }
    var partOfSpeech by remember { mutableStateOf("") }
    var meaningZh by remember { mutableStateOf("") }
    var exampleEn by remember { mutableStateOf("") }

    Column(Modifier.fillMaxSize().padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Text("Local Words", style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold)
        Card(colors = CardDefaults.cardColors(containerColor = Color(0xFFF4F7F3))) {
            Column(Modifier.fillMaxWidth().padding(14.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text("添加单词", fontWeight = FontWeight.Bold)
                Text(activeBank?.title ?: "会自动创建 Personal Words 本机词库。", color = Color(0xFF60706A))
                OutlinedTextField(value = word, onValueChange = { word = it }, label = { Text("word") }, modifier = Modifier.fillMaxWidth())
                OutlinedTextField(value = partOfSpeech, onValueChange = { partOfSpeech = it }, label = { Text("词性") }, modifier = Modifier.fillMaxWidth())
                OutlinedTextField(value = meaningZh, onValueChange = { meaningZh = it }, label = { Text("释义") }, modifier = Modifier.fillMaxWidth())
                OutlinedTextField(value = exampleEn, onValueChange = { exampleEn = it }, label = { Text("例句") }, modifier = Modifier.fillMaxWidth())
                Button(
                    onClick = {
                        viewModel.addLocalWord(word, partOfSpeech, meaningZh, exampleEn)
                        if (word.isNotBlank() && meaningZh.isNotBlank()) {
                            word = ""
                            partOfSpeech = ""
                            meaningZh = ""
                            exampleEn = ""
                        }
                    },
                    modifier = Modifier.fillMaxWidth()
                ) {
                    Text("保存到本机")
                }
                if (wordEditMessage.isNotBlank()) Text(wordEditMessage, color = MaterialTheme.colorScheme.primary)
            }
        }
        OutlinedTextField(
            value = query,
            onValueChange = {
                query = it
                viewModel.search(it)
            },
            modifier = Modifier.fillMaxWidth(),
            label = { Text("Search word or meaning") }
        )
        LazyColumn(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            if (words.isEmpty()) {
                item { EmptyState("No words in the active local bank.") }
            }
            items(words, key = { it.id }) { word ->
                WordRow(word, onDelete = { viewModel.deleteWord(word.id) })
            }
        }
    }
}

@Composable
private fun WordRow(word: WordEntity, onDelete: () -> Unit) {
    Card {
        Row(
            modifier = Modifier.fillMaxWidth().padding(14.dp),
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                Text(word.word, fontWeight = FontWeight.Bold)
                Text(listOf(word.partOfSpeech, word.meaningZh).filter { it.isNotBlank() }.joinToString(" "))
                if (word.exampleEn.isNotBlank()) Text(word.exampleEn, color = Color(0xFF60706A))
            }
            OutlinedButton(onClick = onDelete) {
                Icon(Icons.Default.Delete, contentDescription = "Delete")
            }
        }
    }
}

@Composable
private fun SettingsScreen(viewModel: AppViewModel) {
    val settings by viewModel.settings.collectAsState()
    var newWords by remember(settings) { mutableStateOf(settings.newWordsPerDay.toString()) }
    var reviewLimit by remember(settings) { mutableStateOf(settings.reviewLimit.toString()) }
    var apiBaseUrl by remember(settings) { mutableStateOf(settings.apiBaseUrl) }

    Column(Modifier.fillMaxSize().padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Text("Settings", style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold)
        OutlinedTextField(value = newWords, onValueChange = { newWords = it }, label = { Text("Daily new words") }, modifier = Modifier.fillMaxWidth())
        OutlinedTextField(value = reviewLimit, onValueChange = { reviewLimit = it }, label = { Text("Daily review limit") }, modifier = Modifier.fillMaxWidth())
        OutlinedTextField(value = apiBaseUrl, onValueChange = { apiBaseUrl = it }, label = { Text("Cloud API URL") }, modifier = Modifier.fillMaxWidth())
        Button(
            onClick = {
                viewModel.saveSettings(
                    newWords.toIntOrNull() ?: 80,
                    reviewLimit.toIntOrNull() ?: 80,
                    apiBaseUrl
                )
            },
            modifier = Modifier.fillMaxWidth()
        ) { Text("Save") }
    }
}

@Composable
private fun EmptyState(text: String) {
    Card(colors = CardDefaults.cardColors(containerColor = Color(0xFFF7F2EA))) {
        Text(text, modifier = Modifier.padding(18.dp))
    }
}

private fun WordEntity.options(pool: List<WordEntity>): List<String> {
    val wrong = pool
        .asSequence()
        .filter { it.id != id && it.meaningZh.isNotBlank() && it.meaningZh != meaningZh }
        .map { it.meaningZh }
        .distinct()
        .take(3)
        .toList()
    return (wrong + meaningZh).shuffled()
}
