package com.vocabapp.wordapp.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.vocabapp.wordapp.data.CloudWordBank
import com.vocabapp.wordapp.data.StudyPlan
import com.vocabapp.wordapp.data.StudyWord
import com.vocabapp.wordapp.data.WordBankEntity
import com.vocabapp.wordapp.data.WordEntity
import com.vocabapp.wordapp.data.WordRepository
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.flatMapLatest
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.launch

data class SettingsUiState(
    val newWordsPerDay: Int = 80,
    val reviewLimit: Int = 80,
    val apiBaseUrl: String = "https://layer-city.com/wordapp/api"
)

data class DownloadUiState(
    val cloudBanks: List<CloudWordBank> = emptyList(),
    val loading: Boolean = false,
    val message: String = ""
)

@OptIn(ExperimentalCoroutinesApi::class)
class AppViewModel(
    private val repository: WordRepository,
    private val settingsStore: SettingsStore
) : ViewModel() {
    private val searchQuery = MutableStateFlow("")
    private val activeBankCode = MutableStateFlow("")

    val localBanks: StateFlow<List<WordBankEntity>> = repository.observeBanks()
        .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5_000), emptyList())

    val words: StateFlow<List<WordEntity>> = activeBankCode
        .flatMapLatest { code -> searchQuery.flatMapLatest { query -> repository.observeWords(code, query) } }
        .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5_000), emptyList())

    val studyPlan = MutableStateFlow(StudyPlan(emptyList(), emptyList()))
    val settings = MutableStateFlow(SettingsUiState(settingsStore.newWordsPerDay, settingsStore.reviewLimit, settingsStore.apiBaseUrl))
    val download = MutableStateFlow(DownloadUiState())
    val activeBank = MutableStateFlow<WordBankEntity?>(null)
    val answerMessage = MutableStateFlow("")
    val wordEditMessage = MutableStateFlow("")

    init {
        refreshActiveBank()
        refreshCloudBanks()
    }

    fun search(query: String) {
        searchQuery.value = query
    }

    fun refreshCloudBanks() {
        viewModelScope.launch {
            download.value = download.value.copy(loading = true, message = "")
            runCatching { repository.fetchCloudBanks(settingsStore.apiBaseUrl) }
                .onSuccess { download.value = DownloadUiState(cloudBanks = it, message = "Cloud word banks loaded.") }
                .onFailure { download.value = DownloadUiState(message = "Cloud connection failed: ${it.message ?: "unknown error"}") }
        }
    }

    fun downloadBank(bank: CloudWordBank) {
        viewModelScope.launch {
            download.value = download.value.copy(loading = true, message = "Downloading ${bank.title}...")
            runCatching { repository.downloadBank(settingsStore.apiBaseUrl, bank) }
                .onSuccess {
                    download.value = download.value.copy(loading = false, message = "Downloaded ${bank.title}: $it words.")
                    refreshActiveBank()
                }
                .onFailure { download.value = download.value.copy(loading = false, message = "Download failed: ${it.message ?: "unknown error"}") }
        }
    }

    fun setActiveBank(code: String) {
        viewModelScope.launch {
            repository.setActiveBank(code)
            refreshActiveBank()
        }
    }

    fun reloadPlan() {
        viewModelScope.launch {
            val bank = activeBank.value ?: repository.activeBank()
            if (bank == null) {
                studyPlan.value = StudyPlan(emptyList(), emptyList())
                return@launch
            }
            activeBank.value = bank
            activeBankCode.value = bank.code
            studyPlan.value = repository.todayPlan(bank.code, settingsStore.newWordsPerDay, settingsStore.reviewLimit)
        }
    }

    fun answer(studyWord: StudyWord, chosenMeaning: String) {
        viewModelScope.launch {
            val correct = repository.answer(studyWord, chosenMeaning)
            answerMessage.value = if (correct) "Correct." else "Wrong. Correct answer: ${studyWord.word.meaningZh}"
            reloadPlan()
        }
    }

    fun deleteWord(id: Long) {
        viewModelScope.launch {
            repository.deleteWord(id)
            reloadPlan()
        }
    }

    fun addLocalWord(word: String, partOfSpeech: String, meaningZh: String, exampleEn: String) {
        viewModelScope.launch {
            val saved = repository.addLocalWord(activeBank.value?.code, word, partOfSpeech, meaningZh, exampleEn)
            wordEditMessage.value = if (saved) "Word saved on this phone." else "Word and meaning are required."
            refreshActiveBank()
        }
    }

    fun saveSettings(newWordsPerDay: Int, reviewLimit: Int, apiBaseUrl: String) {
        settingsStore.newWordsPerDay = newWordsPerDay
        settingsStore.reviewLimit = reviewLimit
        settingsStore.apiBaseUrl = apiBaseUrl
        settings.value = SettingsUiState(settingsStore.newWordsPerDay, settingsStore.reviewLimit, settingsStore.apiBaseUrl)
        refreshCloudBanks()
        reloadPlan()
    }

    private fun refreshActiveBank() {
        viewModelScope.launch {
            val bank = repository.activeBank()
            activeBank.value = bank
            activeBankCode.value = bank?.code.orEmpty()
            reloadPlan()
        }
    }
}

class AppViewModelFactory(
    private val repository: WordRepository,
    private val settingsStore: SettingsStore
) : ViewModelProvider.Factory {
    @Suppress("UNCHECKED_CAST")
    override fun <T : ViewModel> create(modelClass: Class<T>): T {
        return AppViewModel(repository, settingsStore) as T
    }
}
