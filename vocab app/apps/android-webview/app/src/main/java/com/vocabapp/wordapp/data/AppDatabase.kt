package com.vocabapp.wordapp.data

import android.content.Context
import androidx.room.Database
import androidx.room.Room
import androidx.room.RoomDatabase

@Database(
    entities = [
        WordBankEntity::class,
        WordEntity::class,
        ReviewEntity::class,
        DailyAnswerEntity::class,
        AnswerAttemptEntity::class
    ],
    version = 1,
    exportSchema = false
)
abstract class AppDatabase : RoomDatabase() {
    abstract fun wordBankDao(): WordBankDao
    abstract fun wordDao(): WordDao
    abstract fun reviewDao(): ReviewDao
    abstract fun answerDao(): AnswerDao

    companion object {
        fun create(context: Context): AppDatabase =
            Room.databaseBuilder(context, AppDatabase::class.java, "wordapp-local.db")
                .fallbackToDestructiveMigration()
                .build()
    }
}
