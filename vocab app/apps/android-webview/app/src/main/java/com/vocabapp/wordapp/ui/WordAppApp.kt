package com.vocabapp.wordapp.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CloudDownload
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.List
import androidx.compose.material.icons.filled.Settings
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
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
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
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

private val PageBg = Color(0xFFF3F3F3)
private val Ink = Color(0xFF1F2937)
private val Muted = Color(0xFF596575)
private val Green = Color(0xFF2E7D32)
private val SoftBlue = Color(0xFFD9E9FF)
private val SoftPurple = Color(0xFFEEDBFF)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun WordAppApp(viewModel: AppViewModel) {
    var tab by remember { mutableStateOf(Tab.Today) }

    MaterialTheme(
        colorScheme = MaterialTheme.colorScheme.copy(
            primary = Green,
            secondary = Color(0xFF6D5B98),
            tertiary = Color(0xFFC47A3B),
            background = PageBg
        )
    ) {
        Surface(modifier = Modifier.fillMaxSize(), color = PageBg) {
            Scaffold(
                containerColor = PageBg,
                topBar = {
                    TopAppBar(
                        title = { Text("WordApp", fontWeight = FontWeight.Bold, color = Color.Black) },
                        colors = TopAppBarDefaults.topAppBarColors(containerColor = Color.White)
                    )
                },
                bottomBar = {
                    NavigationBar(containerColor = Color.White) {
                        NavigationBarItem(selected = tab == Tab.Today, onClick = { tab = Tab.Today }, icon = { Icon(Icons.Default.Home, null) }, label = { Text("今日") })
                        NavigationBarItem(selected = tab == Tab.Banks, onClick = { tab = Tab.Banks }, icon = { Icon(Icons.Default.CloudDownload, null) }, label = { Text("词库") })
                        NavigationBarItem(selected = tab == Tab.Library, onClick = { tab = Tab.Library }, icon = { Icon(Icons.Default.List, null) }, label = { Text("单词") })
                        NavigationBarItem(selected = tab == Tab.Settings, onClick = { tab = Tab.Settings }, icon = { Icon(Icons.Default.Settings, null) }, label = { Text("设置") })
                    }
                }
            ) { padding ->
                Box(Modifier.padding(padding).fillMaxSize().background(PageBg)) {
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
    val settings by viewModel.settings.collectAsState()
    val answerMessage by viewModel.answerMessage.collectAsState()
    var index by remember(plan.all) { mutableIntStateOf(0) }
    val current = plan.all.getOrNull(index)
    val newLimit = settings.newWordsPerDay.coerceAtLeast(1)
    val reviewLimit = settings.reviewLimit.coerceAtLeast(1)
    val progress = ((plan.newWords.size + plan.dueReviews.size).toFloat() / (newLimit + reviewLimit).toFloat()).coerceIn(0f, 1f)

    LazyColumn(
        modifier = Modifier.fillMaxSize().padding(horizontal = 16.dp, vertical = 14.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp)
    ) {
        item {
            ProgressCard(
                newText = "新词 ${plan.newWords.size} / $newLimit",
                reviewText = "复习 ${plan.dueReviews.size} / $reviewLimit",
                progress = progress
            )
        }

        if (activeBank == null) {
            item {
                EmptyState("先下载一个词库，或在“单词”页手动添加自己的单词。")
                Button(onClick = onOpenBanks, modifier = Modifier.fillMaxWidth().padding(top = 10.dp)) {
                    Text("去下载词库")
                }
            }
        } else if (current == null) {
            item { EmptyState("今天没有待学习单词。你可以去“单词”页添加新词。") }
        } else {
            item { StudyCard(current) }
            items(current.word.options(words)) { option ->
                OutlinedButton(
                    onClick = {
                        viewModel.answer(current, option)
                        index = (index + 1).coerceAtMost(plan.all.size - 1)
                    },
                    modifier = Modifier.fillMaxWidth().height(72.dp),
                    shape = RoundedCornerShape(8.dp),
                    colors = ButtonDefaults.outlinedButtonColors(containerColor = Color.White, contentColor = Color.Black)
                ) {
                    Text(option, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold, textAlign = TextAlign.Center)
                }
            }
            if (answerMessage.isNotBlank()) {
                item { Text(answerMessage, color = Green, fontWeight = FontWeight.SemiBold) }
            }
        }
    }
}

@Composable
private fun ProgressCard(newText: String, reviewText: String, progress: Float) {
    Card(
        shape = RoundedCornerShape(0.dp, 0.dp, 16.dp, 16.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
    ) {
        Column(Modifier.fillMaxWidth().padding(20.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                Text(newText, color = Muted, style = MaterialTheme.typography.titleMedium)
                Text(reviewText, color = Muted, style = MaterialTheme.typography.titleMedium)
            }
            LinearProgressIndicator(
                progress = progress,
                modifier = Modifier.fillMaxWidth().height(10.dp),
                color = Green,
                trackColor = Color(0xFFE0E0E0)
            )
        }
    }
}

@Composable
private fun StudyCard(studyWord: StudyWord) {
    Card(
        modifier = Modifier.fillMaxWidth().padding(top = 16.dp, bottom = 16.dp),
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White),
        elevation = CardDefaults.cardElevation(defaultElevation = 6.dp)
    ) {
        Column(
            modifier = Modifier.fillMaxWidth().padding(horizontal = 22.dp, vertical = 34.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(18.dp)
        ) {
            Text(studyWord.word.word, style = MaterialTheme.typography.headlineLarge, fontWeight = FontWeight.Bold, color = Ink)
            if (studyWord.word.partOfSpeech.isNotBlank()) {
                Text(
                    studyWord.word.partOfSpeech,
                    modifier = Modifier.background(SoftPurple, RoundedCornerShape(999.dp)).padding(horizontal = 18.dp, vertical = 8.dp),
                    color = Color(0xFF7A38C2),
                    fontWeight = FontWeight.SemiBold
                )
            }
            if (studyWord.word.exampleEn.isNotBlank()) {
                Text(studyWord.word.exampleEn, color = Muted, style = MaterialTheme.typography.titleMedium, textAlign = TextAlign.Center)
            }
            Text(
                if (studyWord.isDueReview) "复习" else "新词",
                modifier = Modifier.background(SoftBlue, RoundedCornerShape(999.dp)).padding(horizontal = 18.dp, vertical = 8.dp),
                color = Color(0xFF1D5FBF),
                fontWeight = FontWeight.SemiBold
            )
        }
    }
}

@Composable
private fun BanksScreen(viewModel: AppViewModel) {
    val localBanks by viewModel.localBanks.collectAsState()
    val download by viewModel.download.collectAsState()

    LazyColumn(Modifier.fillMaxSize().padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        item {
            Text("词库", style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold)
            Text("下载后单词保存在本机，学习进度不会上传。", color = Muted)
            if (download.loading) LinearProgressIndicator(Modifier.fillMaxWidth().padding(top = 8.dp))
            if (download.message.isNotBlank()) Text(download.message, color = Green)
            OutlinedButton(onClick = { viewModel.refreshCloudBanks() }, modifier = Modifier.fillMaxWidth().padding(top = 8.dp)) {
                Text("刷新云端词库")
            }
        }
        items(download.cloudBanks, key = { it.code }) { bank -> CloudBankRow(bank) { viewModel.downloadBank(bank) } }
        item { Text("本机词库", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold) }
        if (localBanks.isEmpty()) {
            item { EmptyState("本机还没有词库。") }
        } else {
            items(localBanks, key = { it.code }) { bank -> LocalBankRow(bank) { viewModel.setActiveBank(bank.code) } }
        }
    }
}

@Composable
private fun CloudBankRow(bank: CloudWordBank, onDownload: () -> Unit) {
    Card(shape = RoundedCornerShape(12.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
        Column(Modifier.fillMaxWidth().padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Text(bank.title, fontWeight = FontWeight.Bold)
            Text("${bank.wordCount} 个单词", color = Muted)
            if (bank.description.isNotBlank()) Text(bank.description, color = Muted)
            Button(onClick = onDownload, modifier = Modifier.fillMaxWidth()) { Text("下载") }
        }
    }
}

@Composable
private fun LocalBankRow(bank: WordBankEntity, onActivate: () -> Unit) {
    Card(shape = RoundedCornerShape(12.dp), colors = CardDefaults.cardColors(containerColor = if (bank.active) Color(0xFFE5F2ED) else Color.White)) {
        Column(Modifier.fillMaxWidth().padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Text(bank.title, fontWeight = FontWeight.Bold)
            Text("${bank.wordCount} 个单词" + if (bank.active) " · 使用中" else "", color = Muted)
            OutlinedButton(onClick = onActivate, modifier = Modifier.fillMaxWidth()) { Text("设为当前词库") }
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

    LazyColumn(Modifier.fillMaxSize().padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        item {
            Text("单词", style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold)
            Card(shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                Column(Modifier.fillMaxWidth().padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text("添加单词", fontWeight = FontWeight.Bold)
                    Text(activeBank?.title ?: "会自动创建 Personal Words 本机词库。", color = Muted)
                    OutlinedTextField(value = word, onValueChange = { word = it }, label = { Text("word") }, modifier = Modifier.fillMaxWidth())
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                        OutlinedTextField(value = partOfSpeech, onValueChange = { partOfSpeech = it }, label = { Text("词性") }, modifier = Modifier.weight(1f))
                        OutlinedTextField(value = meaningZh, onValueChange = { meaningZh = it }, label = { Text("释义") }, modifier = Modifier.weight(2f))
                    }
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
                    ) { Text("保存到本机") }
                    if (wordEditMessage.isNotBlank()) Text(wordEditMessage, color = Green)
                }
            }
        }
        item {
            OutlinedTextField(
                value = query,
                onValueChange = {
                    query = it
                    viewModel.search(it)
                },
                modifier = Modifier.fillMaxWidth(),
                label = { Text("搜索 word 或释义") }
            )
        }
        if (words.isEmpty()) {
            item { EmptyState("当前词库还没有单词。") }
        } else {
            items(words, key = { it.id }) { wordItem ->
                WordRow(wordItem, onDelete = { viewModel.deleteWord(wordItem.id) })
            }
        }
    }
}

@Composable
private fun WordRow(word: WordEntity, onDelete: () -> Unit) {
    Card(shape = RoundedCornerShape(12.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
        Row(
            modifier = Modifier.fillMaxWidth().padding(16.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                Text(word.word, fontWeight = FontWeight.Bold)
                Text(listOf(word.partOfSpeech, word.meaningZh).filter { it.isNotBlank() }.joinToString(" "), color = Ink)
                if (word.exampleEn.isNotBlank()) Text(word.exampleEn, color = Muted)
            }
            Spacer(Modifier.width(8.dp))
            OutlinedButton(onClick = onDelete) {
                Icon(Icons.Default.Delete, contentDescription = "删除")
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
        Text("设置", style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold)
        OutlinedTextField(value = newWords, onValueChange = { newWords = it }, label = { Text("每日新词数") }, modifier = Modifier.fillMaxWidth())
        OutlinedTextField(value = reviewLimit, onValueChange = { reviewLimit = it }, label = { Text("每日复习上限") }, modifier = Modifier.fillMaxWidth())
        OutlinedTextField(value = apiBaseUrl, onValueChange = { apiBaseUrl = it }, label = { Text("云端 API 地址") }, modifier = Modifier.fillMaxWidth())
        Button(
            onClick = {
                viewModel.saveSettings(
                    newWords.toIntOrNull() ?: 80,
                    reviewLimit.toIntOrNull() ?: 80,
                    apiBaseUrl
                )
            },
            modifier = Modifier.fillMaxWidth()
        ) { Text("保存") }
    }
}

@Composable
private fun EmptyState(text: String) {
    Card(shape = RoundedCornerShape(14.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
        Text(text, modifier = Modifier.padding(18.dp), color = Muted)
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
