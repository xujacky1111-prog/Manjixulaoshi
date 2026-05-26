package com.vocabapp.wordapp

import android.app.Application
import com.vocabapp.wordapp.data.AppDatabase
import com.vocabapp.wordapp.data.WordRepository

class WordAppApplication : Application() {
    val database by lazy { AppDatabase.create(this) }
    val repository by lazy { WordRepository(database) }
}
