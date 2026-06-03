package com.qa365.mobile

import android.media.MediaPlayer
import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.PlayArrow
import androidx.compose.material.icons.filled.Stop
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

@Composable
fun AudioPlayer(url: String, token: String?) {
    var isPlaying by remember { mutableStateOf(false) }
    var isLoading by remember { mutableStateOf(false) }
    var mediaPlayer by remember { mutableStateOf<MediaPlayer?>(null) }
    var errorMessage by remember { mutableStateOf<String?>(null) }
    val context = LocalContext.current
    val coroutineScope = rememberCoroutineScope()

    DisposableEffect(Unit) {
        onDispose {
            mediaPlayer?.stop()
            mediaPlayer?.release()
        }
    }

    Card(modifier = Modifier.fillMaxWidth().padding(vertical = 8.dp)) {
        Column(modifier = Modifier.padding(16.dp)) {
            Text("Reproductor de Audio", style = MaterialTheme.typography.titleMedium)
            Spacer(modifier = Modifier.height(16.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Button(
                    onClick = {
                        if (isPlaying) {
                            mediaPlayer?.stop()
                            mediaPlayer?.release()
                            mediaPlayer = null
                            isPlaying = false
                        } else {
                            isLoading = true
                            errorMessage = null
                            coroutineScope.launch {
                                try {
                                    val player = MediaPlayer()
                                    val headers = mutableMapOf<String, String>()
                                    if (!token.isNullOrEmpty()) {
                                        headers["Authorization"] = "Bearer $token"
                                    }
                                    player.setDataSource(context, android.net.Uri.parse(url), headers)
                                    player.setOnPreparedListener {
                                        isLoading = false
                                        it.start()
                                        isPlaying = true
                                    }
                                    player.setOnCompletionListener {
                                        isPlaying = false
                                        it.release()
                                        mediaPlayer = null
                                    }
                                    player.setOnErrorListener { _, _, _ ->
                                        isLoading = false
                                        isPlaying = false
                                        errorMessage = "Error al reproducir el audio."
                                        true
                                    }
                                    mediaPlayer = player
                                    player.prepareAsync()
                                } catch (e: Exception) {
                                    isLoading = false
                                    errorMessage = "Error: ${e.message}"
                                }
                            }
                        }
                    },
                    modifier = Modifier.height(50.dp)
                ) {
                    if (isLoading) {
                        CircularProgressIndicator(modifier = Modifier.size(24.dp), color = MaterialTheme.colorScheme.onPrimary)
                    } else {
                        Icon(if (isPlaying) Icons.Default.Stop else Icons.Default.PlayArrow, contentDescription = null)
                        Spacer(modifier = Modifier.width(8.dp))
                        Text(if (isPlaying) "Detener" else "Reproducir Audio")
                    }
                }
            }
            if (errorMessage != null) {
                Spacer(modifier = Modifier.height(8.dp))
                Text(text = errorMessage!!, color = MaterialTheme.colorScheme.error, style = MaterialTheme.typography.bodySmall)
            }
        }
    }
}
