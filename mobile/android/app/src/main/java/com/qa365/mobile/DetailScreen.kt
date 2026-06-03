package com.qa365.mobile

import android.content.Context
import androidx.compose.animation.animateContentSize
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import kotlinx.coroutines.launch
import org.json.JSONArray
import org.json.JSONObject

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun DetailScreen(
    type: String,
    data: JSONObject,
    token: String?,
    serverUrl: String,
    isAgent: Boolean = false,
    onNavigate: (String, JSONObject) -> Unit,
    onBack: () -> Unit
) {
    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Text(
                        text = getDetailTitle(type),
                        fontWeight = FontWeight.Bold,
                        fontSize = 18.sp
                    )
                },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(Icons.Default.ArrowBack, contentDescription = "Volver")
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MaterialTheme.colorScheme.background,
                    titleContentColor = MaterialTheme.colorScheme.onBackground
                )
            )
        }
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .verticalScroll(rememberScrollState())
        ) {
            when (type) {
                "evaluation" -> EvaluationDetail(data, token, serverUrl, isAgent)
                "transcript" -> TranscriptDetail(data, token)
                "campaign" -> CampaignDetail(data)
                "quality_form_list" -> QualityFormsModule(data, onNavigate)
                "insight_list" -> InsightsModule(data, onNavigate)
                "quality_form" -> QualityFormDetail(data)
                "insight" -> InsightDetail(data)
                else -> Box(modifier = Modifier.fillMaxSize().padding(32.dp), contentAlignment = Alignment.Center) {
                    Text("Detalle no soportado.")
                }
            }
        }
    }
}

fun getDetailTitle(type: String): String {
    return when (type) {
        "evaluation" -> "Evaluación"
        "transcript" -> "Transcripción"
        "campaign" -> "Campaña"
        "quality_form_list" -> "Fichas de Calidad"
        "insight_list" -> "Insights de IA"
        "quality_form" -> "Detalle de Ficha"
        "insight" -> "Detalle de Insight"
        else -> "Detalle"
    }
}

// ─────────────────────────────────────────────────────────────────────
// EVALUATION DETAIL — Premium evaluation view
// ─────────────────────────────────────────────────────────────────────
@Composable
fun EvaluationDetail(evaluation: JSONObject, token: String?, serverUrl: String, isAgent: Boolean) {
    val score = evaluation.optDouble("score", -1.0)
    var isSubmitting by remember { mutableStateOf(false) }
    var comment by remember { mutableStateOf("") }
    var feedbackMessage by remember { mutableStateOf<String?>(null) }
    var showSuccess by remember { mutableStateOf(false) }
    val coroutineScope = rememberCoroutineScope()

    Column(modifier = Modifier.padding(16.dp)) {

        // Hero Score Card aligned with web gradients
        val scoreColor = getScoreColor(score)
        val gradientColors = when {
            score >= 85 -> listOf(Color(0xFF059669), Color(0xFF10B981))
            score >= 70 -> listOf(Color(0xFFD97706), Color(0xFFF59E0B))
            score >= 0 -> listOf(Color(0xFFDC2626), Color(0xFFEF4444))
            else -> listOf(MaterialTheme.colorScheme.primary, MaterialTheme.colorScheme.secondary)
        }

        Card(
            modifier = Modifier
                .fillMaxWidth()
                .border(width = 1.dp, color = MaterialTheme.colorScheme.outline, shape = RoundedCornerShape(16.dp)),
            shape = RoundedCornerShape(16.dp),
            colors = CardDefaults.cardColors(containerColor = Color.Transparent),
            elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
        ) {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(
                        brush = Brush.linearGradient(colors = gradientColors),
                        shape = RoundedCornerShape(16.dp)
                    )
                    .padding(20.dp)
            ) {
                Column {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Column {
                            Text(
                                text = evaluation.optString("campaign", "Sin campaña"),
                                fontSize = 14.sp,
                                color = Color.White.copy(alpha = 0.8f),
                                fontWeight = FontWeight.Medium
                            )
                            Spacer(modifier = Modifier.height(4.dp))
                            Text(
                                text = "Calificación General",
                                fontSize = 12.sp,
                                color = Color.White.copy(alpha = 0.6f)
                            )
                        }
                        Text(
                            text = if (score < 0) "—" else String.format("%.1f%%", score),
                            fontSize = 38.sp,
                            fontWeight = FontWeight.Black,
                            color = Color.White
                        )
                    }
                    Spacer(modifier = Modifier.height(14.dp))
                    
                    LinearProgressIndicator(
                        progress = { if (score < 0) 0f else (score / 100.0).toFloat().coerceIn(0f, 1f) },
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(6.dp)
                            .clip(RoundedCornerShape(3.dp)),
                        color = Color.White,
                        trackColor = Color.White.copy(alpha = 0.25f)
                    )
                    
                    Spacer(modifier = Modifier.height(14.dp))
                    
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        EvalInfoPill(Icons.Default.Person, evaluation.optString("agent", "—"))
                        EvalInfoPill(Icons.Default.Shield, evaluation.optString("evaluator", "—"))
                    }
                }
            }
        }

        Spacer(modifier = Modifier.height(12.dp))
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            StatusChip(evaluation.optString("status_label", "Sin estado"), evaluation.optString("status", ""))
            val sourceType = evaluation.optString("source_type", "")
            if (sourceType.isNotEmpty()) {
                LabelChip(
                    text = if (sourceType == "audio") "Audio" else "Chat",
                    color = MaterialTheme.colorScheme.primary,
                    icon = if (sourceType == "audio") Icons.Default.Audiotrack else Icons.Default.Chat
                )
            }
        }

        // Advanced Audio Player
        val audioUrl = evaluation.optString("audio_url", "")
        if (audioUrl.isNotEmpty()) {
            Spacer(modifier = Modifier.height(16.dp))
            AudioPlayer(url = audioUrl, token = token)
        }

        // AI Summary (collapsible, no emojis, uses AutoAwesome icon)
        val summary = evaluation.optString("summary", "")
        if (summary.isNotEmpty()) {
            Spacer(modifier = Modifier.height(20.dp))
            var aiSummaryExpanded by remember { mutableStateOf(false) }
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .border(width = 1.dp, color = MaterialTheme.colorScheme.outline, shape = RoundedCornerShape(12.dp))
                    .clickable { aiSummaryExpanded = !aiSummaryExpanded },
                shape = RoundedCornerShape(12.dp),
                colors = CardDefaults.cardColors(
                    containerColor = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.3f)
                )
            ) {
                Column(
                    modifier = Modifier
                        .animateContentSize()
                        .padding(16.dp)
                ) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(8.dp)
                        ) {
                            Icon(
                                imageVector = Icons.Default.AutoAwesome,
                                contentDescription = null,
                                tint = MaterialTheme.colorScheme.primary,
                                modifier = Modifier.size(20.dp)
                            )
                            Text(
                                text = "Resumen de IA",
                                fontSize = 15.sp,
                                fontWeight = FontWeight.Bold,
                                color = MaterialTheme.colorScheme.onBackground
                            )
                        }
                        Icon(
                            imageVector = if (aiSummaryExpanded) Icons.Default.ExpandLess else Icons.Default.ExpandMore,
                            contentDescription = if (aiSummaryExpanded) "Colapsar" else "Expandir",
                            tint = MaterialTheme.colorScheme.onBackground.copy(alpha = 0.6f)
                        )
                    }
                    if (aiSummaryExpanded) {
                        Spacer(modifier = Modifier.height(12.dp))
                        Text(
                            text = summary.replace("**", "").replace("###", "").replace("##", "").replace("#", "").trim(),
                            fontSize = 14.sp,
                            color = MaterialTheme.colorScheme.onBackground,
                            lineHeight = 22.sp
                        )
                    }
                }
            }
        }

        // Criteria Items Accordion List
        val items = evaluation.optJSONArray("items")
        if (items != null && items.length() > 0) {
            Spacer(modifier = Modifier.height(20.dp))
            SectionHeaderIcon(icon = Icons.Default.Checklist, title = "Criterios de Evaluación")
            Spacer(modifier = Modifier.height(8.dp))
            EvaluationCriteriaList(items = items)
        }

        // Conversation Transcript
        val turns = evaluation.optJSONArray("conversation_turns")
        if (turns != null && turns.length() > 0) {
            Spacer(modifier = Modifier.height(20.dp))
            SectionHeaderIcon(icon = Icons.Default.Chat, title = "Transcripción")
            Spacer(modifier = Modifier.height(8.dp))
            ChatTranscript(turns = turns)
        }

        // Feedback Indicators semantically colored
        val indicators = evaluation.optJSONObject("feedback_indicators")
        if (indicators != null) {
            val hasAny = indicators.keys().asSequence().any { key ->
                val v = indicators.opt(key)
                v != null && v.toString() != "null" && v.toString().isNotEmpty()
            }
            if (hasAny) {
                Spacer(modifier = Modifier.height(20.dp))
                SectionHeaderIcon(icon = Icons.Default.Insights, title = "Indicadores de Calidad")
                Spacer(modifier = Modifier.height(8.dp))
                FeedbackIndicatorsCard(indicators)
            }
        }

        // Agent Response Details
        val response = evaluation.optJSONObject("feedback_response") ?: JSONObject()
        val hasResponded = response.optBoolean("responded")

        if (hasResponded) {
            Spacer(modifier = Modifier.height(20.dp))
            SectionHeaderIcon(
                icon = if (response.optString("type") == "accept") Icons.Default.CheckCircle else Icons.Default.Warning,
                title = "Respuesta del Asesor"
            )
            Spacer(modifier = Modifier.height(8.dp))
            AgentResponseCard(response)
        }

        // Accept / Dispute form matching web inputs and color outlines (no emojis)
        if (isAgent && !hasResponded && !showSuccess && evaluation.optString("status") == "published_to_agent") {
            Spacer(modifier = Modifier.height(20.dp))
            SectionHeaderIcon(icon = Icons.Default.RateReview, title = "Responder a la Evaluación")
            Spacer(modifier = Modifier.height(8.dp))
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .border(width = 1.dp, color = MaterialTheme.colorScheme.outline, shape = RoundedCornerShape(12.dp)),
                shape = RoundedCornerShape(12.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
            ) {
                Column(modifier = Modifier.padding(16.dp)) {
                    Text(
                        text = "Revise los criterios detallados y registre su conformidad o discrepancia. Este registro se sincronizará con la plataforma web.",
                        fontSize = 13.sp,
                        color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.6f),
                        lineHeight = 18.sp
                    )
                    Spacer(modifier = Modifier.height(14.dp))
                    OutlinedTextField(
                        value = comment,
                        onValueChange = { comment = it },
                        label = { Text("Comentarios o justificación de disputa") },
                        modifier = Modifier.fillMaxWidth(),
                        minLines = 3,
                        shape = RoundedCornerShape(8.dp),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = MaterialTheme.colorScheme.primary,
                            unfocusedBorderColor = MaterialTheme.colorScheme.outline,
                            focusedLabelColor = MaterialTheme.colorScheme.primary,
                            unfocusedLabelColor = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f)
                        )
                    )
                    Spacer(modifier = Modifier.height(16.dp))
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(12.dp)
                    ) {
                        Button(
                            onClick = {
                                if (comment.isBlank()) {
                                    feedbackMessage = "Por favor agregue un comentario de conformidad."
                                    return@Button
                                }
                                isSubmitting = true
                                coroutineScope.launch {
                                    try {
                                        val body = JSONObject().apply {
                                            put("response_type", "accept")
                                            put("commitment_comment", comment)
                                        }
                                        Api.request(serverUrl, "/api/mobile/evaluations/${evaluation.optInt("id")}/respond", "POST", body, token)
                                        feedbackMessage = null
                                        showSuccess = true
                                    } catch (e: Exception) {
                                        feedbackMessage = e.message ?: "Error al registrar respuesta"
                                    } finally {
                                        isSubmitting = false
                                    }
                                }
                            },
                            modifier = Modifier.weight(1f).height(44.dp),
                            shape = RoundedCornerShape(8.dp),
                            enabled = !isSubmitting,
                            colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF059669))
                        ) {
                            if (isSubmitting) {
                                CircularProgressIndicator(
                                    modifier = Modifier.size(20.dp),
                                    color = Color.White,
                                    strokeWidth = 2.dp
                                )
                            } else {
                                Icon(Icons.Default.Check, contentDescription = null, modifier = Modifier.size(16.dp))
                                Spacer(modifier = Modifier.width(6.dp))
                                Text("Aceptar", fontWeight = FontWeight.Bold, fontSize = 13.sp)
                            }
                        }
                        
                        OutlinedButton(
                            onClick = {
                                if (comment.isBlank()) {
                                    feedbackMessage = "Por favor agregue el motivo detallado de la disputa."
                                    return@OutlinedButton
                                }
                                isSubmitting = true
                                coroutineScope.launch {
                                    try {
                                        val body = JSONObject().apply {
                                            put("response_type", "dispute")
                                            put("dispute_reason", comment)
                                        }
                                        Api.request(serverUrl, "/api/mobile/evaluations/${evaluation.optInt("id")}/respond", "POST", body, token)
                                        feedbackMessage = null
                                        showSuccess = true
                                    } catch (e: Exception) {
                                        feedbackMessage = e.message ?: "Error al registrar disputa"
                                    } finally {
                                        isSubmitting = false
                                    }
                                }
                            },
                            modifier = Modifier.weight(1f).height(44.dp),
                            shape = RoundedCornerShape(8.dp),
                            enabled = !isSubmitting,
                            colors = ButtonDefaults.outlinedButtonColors(contentColor = Color(0xFFEF4444)),
                            border = BorderStroke(1.dp, Color(0xFFEF4444).copy(alpha = 0.5f))
                        ) {
                            Icon(Icons.Default.Flag, contentDescription = null, modifier = Modifier.size(16.dp))
                            Spacer(modifier = Modifier.width(6.dp))
                            Text("Disputar", fontWeight = FontWeight.Bold, fontSize = 13.sp)
                        }
                    }
                    if (feedbackMessage != null) {
                        Spacer(modifier = Modifier.height(10.dp))
                        Text(
                            text = feedbackMessage!!,
                            color = MaterialTheme.colorScheme.error,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Medium
                        )
                    }
                }
            }
        }

        // Success message overlay box (no emojis)
        if (showSuccess) {
            Spacer(modifier = Modifier.height(16.dp))
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .border(width = 1.dp, color = Color(0xFF059669).copy(alpha = 0.3f), shape = RoundedCornerShape(12.dp)),
                shape = RoundedCornerShape(12.dp),
                colors = CardDefaults.cardColors(containerColor = Color(0xFF059669).copy(alpha = 0.08f))
            ) {
                Row(
                    modifier = Modifier.padding(16.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    Icon(
                        imageVector = Icons.Default.CheckCircle,
                        contentDescription = null,
                        tint = Color(0xFF059669),
                        modifier = Modifier.size(24.dp)
                    )
                    Text(
                        text = "Respuesta registrada y sincronizada correctamente.",
                        fontWeight = FontWeight.Bold,
                        color = Color(0xFF059669),
                        fontSize = 14.sp
                    )
                }
            }
        }

        Spacer(modifier = Modifier.height(24.dp))
    }
}

@Composable
fun EvalInfoPill(icon: androidx.compose.ui.graphics.vector.ImageVector, text: String) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(6.dp)
    ) {
        Icon(icon, contentDescription = null, tint = Color.White.copy(alpha = 0.8f), modifier = Modifier.size(14.dp))
        Text(
            text = text,
            fontSize = 12.sp,
            color = Color.White.copy(alpha = 0.9f),
            fontWeight = FontWeight.Medium,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis
        )
    }
}

@Composable
fun StatusChip(label: String, status: String) {
    val chipColor = when (status) {
        "published_to_agent" -> Color(0xFF3B82F6) // Blue
        "agent_accepted" -> Color(0xFF059669)     // Green
        "agent_disputed" -> Color(0xFFEF4444)     // Red
        "dispute_resolved" -> Color(0xFF7C3AED)   // Purple
        "closed" -> Color.Gray
        else -> Color(0xFFF59E0B)                 // Amber
    }
    Box(
        modifier = Modifier
            .clip(RoundedCornerShape(6.dp))
            .background(chipColor.copy(alpha = 0.1f))
            .border(width = 0.5.dp, color = chipColor.copy(alpha = 0.2f), shape = RoundedCornerShape(6.dp))
            .padding(horizontal = 10.dp, vertical = 4.dp)
    ) {
        Text(text = label, fontSize = 11.sp, fontWeight = FontWeight.Bold, color = chipColor)
    }
}

@Composable
fun SectionHeaderIcon(icon: androidx.compose.ui.graphics.vector.ImageVector, title: String) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        Icon(
            imageVector = icon,
            contentDescription = null,
            tint = MaterialTheme.colorScheme.primary,
            modifier = Modifier.size(20.dp)
        )
        Text(
            text = title,
            fontSize = 16.sp,
            fontWeight = FontWeight.Bold,
            color = MaterialTheme.colorScheme.onBackground
        )
    }
}

@Composable
fun EvaluationCriteriaList(items: JSONArray) {
    val grouped = mutableMapOf<String, MutableList<JSONObject>>()
    for (i in 0 until items.length()) {
        val item = items.optJSONObject(i) ?: continue
        val subattr = item.optJSONObject("subattribute") ?: JSONObject()
        val attrName = subattr.optString("attribute_name", "General")
        grouped.getOrPut(attrName) { mutableListOf() }.add(item)
    }

    grouped.forEach { (attributeName, criteriaItems) ->
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .padding(vertical = 5.dp)
                .border(width = 1.dp, color = MaterialTheme.colorScheme.outline, shape = RoundedCornerShape(12.dp)),
            shape = RoundedCornerShape(12.dp),
            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
            elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
        ) {
            Column(modifier = Modifier.padding(14.dp)) {
                Text(
                    text = attributeName,
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.primary
                )
                Spacer(modifier = Modifier.height(10.dp))

                criteriaItems.forEachIndexed { index, item ->
                    val subattr = item.optJSONObject("subattribute") ?: JSONObject()
                    val status = item.optString("status", "unknown")
                    val subName = subattr.optString("name", "Criterio")
                    val weight = subattr.optDouble("weight_percent", 0.0)
                    val evidence = item.optString("evidence_quote", "")
                    val notes = item.optString("ai_notes", "")
                    val confidence = item.optString("confidence", "")

                    var expanded by remember { mutableStateOf(false) }

                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clickable { expanded = !expanded }
                            .animateContentSize()
                    ) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Row(
                                modifier = Modifier.weight(1f),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(8.dp)
                            ) {
                                val statusIcon = when (status) {
                                    "cumple" -> Icons.Default.CheckCircle
                                    "no_cumple" -> Icons.Default.Cancel
                                    else -> Icons.Default.HelpOutline
                                }
                                val statusColor = when (status) {
                                    "cumple" -> Color(0xFF059669)
                                    "no_cumple" -> Color(0xFFEF4444)
                                    else -> Color.Gray
                                }
                                Icon(
                                    imageVector = statusIcon,
                                    contentDescription = null,
                                    tint = statusColor,
                                    modifier = Modifier.size(18.dp)
                               )
                                Column(modifier = Modifier.weight(1f)) {
                                    Text(
                                        text = subName,
                                        fontSize = 13.sp,
                                        fontWeight = FontWeight.Medium,
                                        color = MaterialTheme.colorScheme.onSurface,
                                        maxLines = 2,
                                        overflow = TextOverflow.Ellipsis
                                    )
                                    if (weight > 0) {
                                        Text(
                                            text = "Peso: ${String.format("%.1f", weight)}%",
                                            fontSize = 11.sp,
                                            color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f)
                                        )
                                    }
                                }
                            }
                            CriteriaStatusTag(status)
                        }

                        if (expanded) {
                            Spacer(modifier = Modifier.height(10.dp))
                            if (evidence.isNotEmpty() && evidence != "null") {
                                Box(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .clip(RoundedCornerShape(8.dp))
                                        .background(MaterialTheme.colorScheme.surfaceVariant)
                                        .border(width = 0.5.dp, color = MaterialTheme.colorScheme.outline, shape = RoundedCornerShape(8.dp))
                                        .padding(12.dp)
                                ) {
                                    Column {
                                        Text(
                                            text = "EVIDENCIA",
                                            fontSize = 10.sp,
                                            fontWeight = FontWeight.Bold,
                                            color = MaterialTheme.colorScheme.primary,
                                            letterSpacing = 0.5.sp
                                        )
                                        Spacer(modifier = Modifier.height(4.dp))
                                        Text(
                                            text = "\"$evidence\"",
                                            fontSize = 12.sp,
                                            fontStyle = FontStyle.Italic,
                                            color = MaterialTheme.colorScheme.onBackground,
                                            lineHeight = 18.sp
                                        )
                                    }
                                }
                            }
                            if (notes.isNotEmpty() && notes != "null") {
                                Spacer(modifier = Modifier.height(8.dp))
                                Row(
                                    verticalAlignment = Alignment.Top,
                                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                                ) {
                                    Icon(
                                        imageVector = Icons.Default.Description,
                                        contentDescription = null,
                                        tint = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.4f),
                                        modifier = Modifier.size(14.dp).padding(top = 2.dp)
                                    )
                                    Text(
                                        text = notes,
                                        fontSize = 12.sp,
                                        color = MaterialTheme.colorScheme.onBackground.copy(alpha = 0.8f),
                                        lineHeight = 18.sp
                                    )
                                }
                            }
                            if (confidence.isNotEmpty() && confidence != "null") {
                                Spacer(modifier = Modifier.height(6.dp))
                                Text(
                                    text = "Confianza del análisis: $confidence",
                                    fontSize = 11.sp,
                                    color = MaterialTheme.colorScheme.onBackground.copy(alpha = 0.4f)
                                )
                            }
                        }

                        if (index < criteriaItems.size - 1) {
                            Divider(
                                modifier = Modifier.padding(vertical = 10.dp),
                                color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.05f)
                            )
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun CriteriaStatusTag(status: String) {
    val (label, color) = when (status) {
        "cumple" -> "Cumple" to Color(0xFF059669)
        "no_cumple" -> "No Cumple" to Color(0xFFEF4444)
        "no_encontrado" -> "No Encontrado" to Color(0xFFF59E0B)
        else -> status.replace("_", " ").replaceFirstChar { it.uppercase() } to Color.Gray
    }
    Box(
        modifier = Modifier
            .clip(RoundedCornerShape(4.dp))
            .background(color.copy(alpha = 0.1f))
            .border(width = 0.5.dp, color = color.copy(alpha = 0.2f), shape = RoundedCornerShape(4.dp))
            .padding(horizontal = 8.dp, vertical = 3.dp)
    ) {
        Text(text = label, fontSize = 10.sp, fontWeight = FontWeight.Bold, color = color)
    }
}

@Composable
fun FeedbackIndicatorsCard(indicators: JSONObject) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .border(width = 1.dp, color = MaterialTheme.colorScheme.outline, shape = RoundedCornerShape(12.dp)),
        shape = RoundedCornerShape(12.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
    ) {
        Column(modifier = Modifier.padding(14.dp)) {
            val keys = listOf(
                "empathy" to "Empatía",
                "active_listening" to "Escucha Activa",
                "objection_handling" to "Manejo de Objeciones",
                "resolution_clarity" to "Claridad de Resolución",
                "speech_control" to "Control del Discurso",
                "customer_experience_risk" to "Riesgo de Experiencia"
            )
            keys.forEachIndexed { index, (key, label) ->
                val value = indicators.optString(key, "")
                if (value.isNotEmpty() && value != "null") {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(vertical = 5.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(label, fontSize = 13.sp, color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.7f))
                        val tagColor = when (value.lowercase()) {
                            "alto", "high", "bueno", "good", "si", "sí", "true" -> Color(0xFF059669)
                            "medio", "medium", "regular" -> Color(0xFFF59E0B)
                            "bajo", "low", "malo", "bad", "critico", "critical" -> Color(0xFFEF4444)
                            "no", "false" -> Color(0xFFEF4444)
                            else -> MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f)
                        }
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(4.dp))
                                .background(tagColor.copy(alpha = 0.1f))
                                .border(width = 0.5.dp, color = tagColor.copy(alpha = 0.2f), shape = RoundedCornerShape(4.dp))
                                .padding(horizontal = 8.dp, vertical = 3.dp)
                        ) {
                            Text(
                                text = value.replaceFirstChar { it.uppercase() },
                                fontSize = 11.sp,
                                fontWeight = FontWeight.Bold,
                                color = tagColor
                            )
                        }
                    }
                    if (index < keys.size - 1) {
                        Divider(color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.04f))
                    }
                }
            }
        }
    }
}

@Composable
fun AgentResponseCard(response: JSONObject) {
    val isAccept = response.optString("type") == "accept"
    val bgColor = if (isAccept) Color(0xFF059669) else Color(0xFFEF4444)

    Card(
        modifier = Modifier
            .fillMaxWidth()
            .border(width = 1.dp, color = bgColor.copy(alpha = 0.3f), shape = RoundedCornerShape(12.dp)),
        shape = RoundedCornerShape(12.dp),
        colors = CardDefaults.cardColors(containerColor = bgColor.copy(alpha = 0.05f)),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                Icon(
                    imageVector = if (isAccept) Icons.Default.CheckCircle else Icons.Default.Warning,
                    contentDescription = null,
                    tint = bgColor,
                    modifier = Modifier.size(20.dp)
                )
                Text(
                    text = if (isAccept) "Conformidad registrada por el asesor" else "Discrepancia registrada por el asesor",
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Bold,
                    color = bgColor
                )
            }
            val commentText = if (isAccept)
                response.optString("commitment_comment", "")
            else
                response.optString("dispute_reason", "")

            if (commentText.isNotEmpty() && commentText != "null") {
                Spacer(modifier = Modifier.height(10.dp))
                Text(
                    text = commentText,
                    fontSize = 13.sp,
                    color = MaterialTheme.colorScheme.onBackground,
                    lineHeight = 18.sp
                )
            }
            val respondedAt = response.optString("responded_at", "")
            if (respondedAt.isNotEmpty() && respondedAt != "null") {
                Spacer(modifier = Modifier.height(8.dp))
                Text(
                    text = "Registrado el: ${respondedAt.take(10)}",
                    fontSize = 11.sp,
                    color = MaterialTheme.colorScheme.onBackground.copy(alpha = 0.4f)
                )
            }
        }
    }
}

// ─────────────────────────────────────────────────────────────────────
// TRANSCRIPT DETAIL
// ─────────────────────────────────────────────────────────────────────
@Composable
fun TranscriptDetail(transcript: JSONObject, token: String?) {
    Column(modifier = Modifier.padding(16.dp)) {
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .border(width = 1.dp, color = MaterialTheme.colorScheme.outline, shape = RoundedCornerShape(12.dp)),
            shape = RoundedCornerShape(12.dp),
            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
            elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
        ) {
            Column(modifier = Modifier.padding(16.dp)) {
                Text(
                    text = transcript.optString("file_name", "Interacción"),
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.primary,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis
                )
                Spacer(modifier = Modifier.height(12.dp))
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    InfoColumn("Campaña", transcript.optString("campaign", "—"))
                    InfoColumn("Asesor", transcript.optString("agent", "—"))
                    InfoColumn("Duración", transcript.optString("duration_label", "00:00"))
                }
            }
        }

        val audioUrl = transcript.optString("audio_url")
        if (transcript.optString("source_type") == "audio" && audioUrl.isNotEmpty()) {
            Spacer(modifier = Modifier.height(16.dp))
            AudioPlayer(url = audioUrl, token = token)
        }

        Spacer(modifier = Modifier.height(20.dp))
        SectionHeaderIcon(icon = Icons.Default.Chat, title = "Detalle de la Conversación")
        Spacer(modifier = Modifier.height(8.dp))

        val turns = transcript.optJSONArray("conversation_turns")
        if (turns != null && turns.length() > 0) {
            ChatTranscript(turns = turns)
        } else {
            val text = transcript.optString("transcript_text", transcript.optString("transcript_excerpt", "Transcripción no disponible."))
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .border(width = 1.dp, color = MaterialTheme.colorScheme.outline, shape = RoundedCornerShape(12.dp)),
                shape = RoundedCornerShape(12.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
            ) {
                Text(
                    text = text,
                    fontSize = 13.sp,
                    modifier = Modifier.padding(16.dp),
                    color = MaterialTheme.colorScheme.onSurface,
                    lineHeight = 20.sp
                )
            }
        }

        Spacer(modifier = Modifier.height(24.dp))
    }
}

@Composable
fun InfoColumn(label: String, value: String) {
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        Text(label, fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f))
        Spacer(modifier = Modifier.height(2.dp))
        Text(
            text = value,
            fontSize = 13.sp,
            fontWeight = FontWeight.Bold,
            color = MaterialTheme.colorScheme.onSurface,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis
        )
    }
}

// ─────────────────────────────────────────────────────────────────────
// CHAT TRANSCRIPT — Premium bubbles matching web theme (no emojis)
// ─────────────────────────────────────────────────────────────────────
@Composable
fun ChatTranscript(turns: JSONArray) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 4.dp),
        verticalArrangement = Arrangement.spacedBy(10.dp)
    ) {
        for (i in 0 until turns.length()) {
            val turn = turns.optJSONObject(i) ?: continue
            val speaker = turn.optString("speaker", "system")
            val label = turn.optString("label", "Sistema")
            val message = turn.optString("message", "")
            val timestamp = turn.optString("timestamp", "")

            if (message.isEmpty()) continue

            when (speaker) {
                "client" -> {
                    Column(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalAlignment = Alignment.End
                    ) {
                        Text(
                            text = "$label  $timestamp".trim(),
                            fontSize = 10.sp,
                            color = MaterialTheme.colorScheme.onBackground.copy(alpha = 0.5f),
                            modifier = Modifier.padding(end = 4.dp, bottom = 2.dp)
                        )
                        Box(
                            modifier = Modifier
                                .widthIn(max = 280.dp)
                                .clip(RoundedCornerShape(12.dp, 2.dp, 12.dp, 12.dp))
                                .background(
                                    Brush.linearGradient(
                                        colors = listOf(
                                            MaterialTheme.colorScheme.primary,
                                            MaterialTheme.colorScheme.secondary
                                        )
                                    )
                                )
                                .padding(horizontal = 14.dp, vertical = 10.dp)
                        ) {
                            Text(
                                text = message,
                                color = Color.White,
                                fontSize = 13.sp,
                                lineHeight = 18.sp
                            )
                        }
                    }
                }
                "agent" -> {
                    Column(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalAlignment = Alignment.Start
                    ) {
                        Text(
                            text = "$timestamp  $label".trim(),
                            fontSize = 10.sp,
                            color = MaterialTheme.colorScheme.onBackground.copy(alpha = 0.5f),
                            modifier = Modifier.padding(start = 4.dp, bottom = 2.dp)
                        )
                        Box(
                            modifier = Modifier
                                .widthIn(max = 280.dp)
                                .clip(RoundedCornerShape(2.dp, 12.dp, 12.dp, 12.dp))
                                .background(MaterialTheme.colorScheme.surfaceVariant)
                                .border(width = 0.5.dp, color = MaterialTheme.colorScheme.outline, shape = RoundedCornerShape(2.dp, 12.dp, 12.dp, 12.dp))
                                .padding(horizontal = 14.dp, vertical = 10.dp)
                        ) {
                            Text(
                                text = message,
                                color = MaterialTheme.colorScheme.onSurface,
                                fontSize = 13.sp,
                                lineHeight = 18.sp
                            )
                        }
                    }
                }
                else -> {
                    // System context pill
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(vertical = 4.dp),
                        contentAlignment = Alignment.Center
                    ) {
                        Box(
                            modifier = Modifier
                                .widthIn(max = 290.dp)
                                .clip(RoundedCornerShape(6.dp))
                                .background(MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.5f))
                                .border(width = 0.5.dp, color = MaterialTheme.colorScheme.outline, shape = RoundedCornerShape(6.dp))
                                .padding(horizontal = 12.dp, vertical = 6.dp),
                            contentAlignment = Alignment.Center
                        ) {
                            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                                Text(
                                    text = label.uppercase(),
                                    fontSize = 9.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.6f),
                                    letterSpacing = 0.5.sp
                                )
                                Spacer(modifier = Modifier.height(2.dp))
                                Text(
                                    text = message,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                    fontSize = 12.sp,
                                    textAlign = TextAlign.Center
                                )
                            }
                        }
                    }
                }
            }
        }
    }
}

// ─────────────────────────────────────────────────────────────────────
// CAMPAIGN DETAIL
// ─────────────────────────────────────────────────────────────────────
@Composable
fun CampaignDetail(campaign: JSONObject) {
    Column(modifier = Modifier.padding(16.dp)) {
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .border(width = 1.dp, color = MaterialTheme.colorScheme.outline, shape = RoundedCornerShape(16.dp)),
            shape = RoundedCornerShape(16.dp),
            colors = CardDefaults.cardColors(containerColor = Color.Transparent)
        ) {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(
                        brush = Brush.linearGradient(
                            colors = listOf(
                                MaterialTheme.colorScheme.primary,
                                MaterialTheme.colorScheme.secondary
                            )
                        ),
                        shape = RoundedCornerShape(16.dp)
                    )
                    .padding(20.dp)
            ) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column {
                        Text(
                            text = campaign.optString("name", "Campaña"),
                            fontSize = 18.sp,
                            fontWeight = FontWeight.Bold,
                            color = Color.White
                        )
                        Spacer(modifier = Modifier.height(4.dp))
                        Text(
                            text = if (campaign.optBoolean("active")) "Campaña Activa" else "Campaña Inactiva",
                            fontSize = 12.sp,
                            color = Color.White.copy(alpha = 0.8f)
                        )
                    }
                    Text(
                        text = campaign.optString("score_label", "0%"),
                        fontSize = 32.sp,
                        fontWeight = FontWeight.Black,
                        color = Color.White
                    )
                }
            }
        }

        Spacer(modifier = Modifier.height(16.dp))

        Card(
            modifier = Modifier
                .fillMaxWidth()
                .border(width = 1.dp, color = MaterialTheme.colorScheme.outline, shape = RoundedCornerShape(12.dp)),
            shape = RoundedCornerShape(12.dp),
            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
            elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
        ) {
            Column(modifier = Modifier.padding(16.dp)) {
                ProgressLine(
                    label = "Calidad Promedio de Campaña",
                    score = campaign.optDouble("average_score", 0.0),
                    color = getScoreColor(campaign.optDouble("average_score", 0.0))
                )
                Spacer(modifier = Modifier.height(14.dp))
                InfoRow("Total Evaluaciones Realizadas", campaign.optString("evaluations", "0"))
                InfoRow("Total Interacciones Recibidas", campaign.optString("interactions", "0"))
                val target = campaign.optDouble("target_quality", -1.0)
                if (target > 0) {
                    InfoRow("Meta de Calidad Establecida", String.format("%.1f%%", target))
                }
            }
        }

        Spacer(modifier = Modifier.height(32.dp))
    }
}

// ─────────────────────────────────────────────────────────────────────
// [NEW] QUALITY FORM DETAIL
// ─────────────────────────────────────────────────────────────────────
@Composable
fun QualityFormDetail(form: JSONObject) {
    Column(modifier = Modifier.padding(16.dp)) {
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .border(width = 1.dp, color = MaterialTheme.colorScheme.outline, shape = RoundedCornerShape(16.dp)),
            shape = RoundedCornerShape(16.dp),
            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
        ) {
            Column(modifier = Modifier.padding(20.dp)) {
                Text(
                    text = form.optString("name", "Formato de Calidad"),
                    fontSize = 16.sp,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.primary
                )
                Spacer(modifier = Modifier.height(10.dp))
                Text(
                    text = "Este formato define la pauta de evaluación operativa configurada en el sistema para la auditoría de interacciones.",
                    fontSize = 13.sp,
                    color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.6f),
                    lineHeight = 18.sp
                )
                Spacer(modifier = Modifier.height(16.dp))
                Divider(color = MaterialTheme.colorScheme.outline.copy(alpha = 0.5f))
                Spacer(modifier = Modifier.height(12.dp))
                
                InfoRow("Operación / Campaña", form.optString("campaign", "General"))
                InfoRow("Versión", "V${form.optInt("versions", 1)}")
                InfoRow("Estado de Versión", form.optString("latest_status", "Activo"))
            }
        }

        val hasContext = form.optBoolean("has_context")
        if (hasContext) {
            Spacer(modifier = Modifier.height(16.dp))
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .border(width = 1.dp, color = MaterialTheme.colorScheme.primary.copy(alpha = 0.3f), shape = RoundedCornerShape(12.dp)),
                shape = RoundedCornerShape(12.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.primary.copy(alpha = 0.05f))
            ) {
                Row(
                    modifier = Modifier.padding(16.dp),
                    verticalAlignment = Alignment.Top,
                    horizontalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    Icon(
                        imageVector = Icons.Default.AutoAwesome,
                        contentDescription = null,
                        tint = MaterialTheme.colorScheme.primary,
                        modifier = Modifier.size(20.dp).padding(top = 2.dp)
                    )
                    Column {
                        Text(
                            text = "Contexto Operativo por IA",
                            fontSize = 14.sp,
                            fontWeight = FontWeight.Bold,
                            color = MaterialTheme.colorScheme.primary
                        )
                        Spacer(modifier = Modifier.height(4.dp))
                        Text(
                            text = "Este formato cuenta con lineamientos específicos de contexto (pautas comerciales y de resolución) integrados en el motor de inteligencia artificial para auditar de forma automática las llamadas y chats.",
                            fontSize = 12.sp,
                            color = MaterialTheme.colorScheme.onBackground.copy(alpha = 0.8f),
                            lineHeight = 16.sp
                        )
                    }
                }
            }
        }
        
        Spacer(modifier = Modifier.height(32.dp))
    }
}

// ─────────────────────────────────────────────────────────────────────
// [NEW] INSIGHT DETAIL
// ─────────────────────────────────────────────────────────────────────
@Composable
fun InsightDetail(insight: JSONObject) {
    Column(modifier = Modifier.padding(16.dp)) {
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .border(width = 1.dp, color = MaterialTheme.colorScheme.outline, shape = RoundedCornerShape(16.dp)),
            shape = RoundedCornerShape(16.dp),
            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
        ) {
            Column(modifier = Modifier.padding(20.dp)) {
                Text(
                    text = "Reporte de Insights IA",
                    fontSize = 16.sp,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.primary
                )
                Spacer(modifier = Modifier.height(4.dp))
                Text(
                    text = "Frecuencia del análisis: ${insight.optString("type", "Campaña").replaceFirstChar { it.uppercase() }}",
                    fontSize = 12.sp,
                    color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f)
                )
                Spacer(modifier = Modifier.height(16.dp))
                Divider(color = MaterialTheme.colorScheme.outline.copy(alpha = 0.5f))
                Spacer(modifier = Modifier.height(12.dp))
                
                InfoRow("Campaña Analizada", insight.optString("campaign", "—"))
                InfoRow("Rango de Fechas", insight.optString("date_range", "—"))
                InfoRow("Hallazgos Identificados", "${insight.optInt("findings", 0)} detectados")
            }
        }

        Spacer(modifier = Modifier.height(16.dp))
        SectionHeaderIcon(icon = Icons.Default.AutoAwesome, title = "Resumen del Reporte")
        Spacer(modifier = Modifier.height(8.dp))
        
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .border(width = 1.dp, color = MaterialTheme.colorScheme.outline, shape = RoundedCornerShape(12.dp)),
            shape = RoundedCornerShape(12.dp),
            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.3f))
        ) {
            Column(modifier = Modifier.padding(16.dp)) {
                Text(
                    text = insight.optString("summary", "Detalles del reporte de insights generado por IA."),
                    fontSize = 13.sp,
                    color = MaterialTheme.colorScheme.onBackground,
                    lineHeight = 20.sp
                )
            }
        }
        
        Spacer(modifier = Modifier.height(32.dp))
    }
}
