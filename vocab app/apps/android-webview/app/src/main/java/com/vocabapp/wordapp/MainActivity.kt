package com.vocabapp.wordapp

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.viewModels
import com.vocabapp.wordapp.ui.WordAppApp
import com.vocabapp.wordapp.viewmodel.AppViewModel
import com.vocabapp.wordapp.viewmodel.AppViewModelFactory
import com.vocabapp.wordapp.viewmodel.SettingsStore

class MainActivity : ComponentActivity() {
    private val viewModel by viewModels<AppViewModel> {
        val app = application as WordAppApplication
        AppViewModelFactory(app.repository, SettingsStore(this))
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            WordAppApp(viewModel)
        }
    }
}
