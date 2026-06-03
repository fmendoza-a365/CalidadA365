package com.qa365.mobile

import android.content.Context
import androidx.compose.animation.animateContentSize
import androidx.compose.foundation.background
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
import androidx.compose.foundation.BorderStroke
import androidx.compose.ui.text.font.FontStyle
import androidx.compose.ui.text.font.FontWeight
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
    onBack: () -> Unit
) {
    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Text(
                        text = getDetailTitle(type),
                        fontWeight = FontWeight.Bold
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
                "evaluation" -> EvaluationDetail(data, token, serverUrl)
                "transcript" -> TranscriptDetail(data, token)
                "campaign" -> CampaignDetail(data)
                else -> Text("Detalle no soportado aún.")
            }
        }
    }
}

fun getDetailTitle(type: String): String {
    return when (type) {
        "evaluation" -> "Evaluación"
        "transcript" -> "Transcripción"
        "campaign" -> "Campaña"
        else -> "Detalle"
    }
}

// ─────────────────────────────────────────────────────────────────────
// EVALUATION DETAIL — Premium full-featured evaluation view
// ─────────────────────────────────────────────────────────────────────
@Composable
fun EvaluationDetail(evaluation: JSONObject, token: String?, serverUrl: String) {
    val score = evaluation.optDouble("score", -1.0)
    var isSubmitting by remember { mutableStateOf(false) }
    var comment by remember { mutableStateOf("") }
    var feedbackMessage by remember { mutableStateOf<String?>(null) }
    var showSuccess by remember { mutableStateOf(false) }
    val coroutineScope = rememberCoroutineScope()

    Column(modifier = Modifier.padding(16.dp)) {

        // ── Hero Score Card ──
        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(20.dp),
            colors = CardDefaults.cardColors(containerColor = Color.Transparent),
            elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
        ) {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(
                        brush = Brush.linearGradient(
                            colors = if (score >= 85) listOf(Color(0xFF059669), Color(0xFF10B981))
                            else if (score >= 70) listOf(Color(0xFFD97706), Color(0xFFF59E0B))
                            else if (score >= 0) listOf(Color(0xFFDC2626), Color(0xFFF43F5E))
                            else listOf(Color(0xFF6366F1), Color(0xFF818CF8))
                        ),
                        shape = RoundedCornerShape(20.dp)
                    )
                    .padding(24.dp)
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
                                text = "Nota General",
                                fontSize = 13.sp,
                                color = Color.White.copy(alpha = 0.6f)
                            )
                        }
                        Text(
                            text = if (score < 0) "—" else String.format("%.1f%%", score),
                            fontSize = 44.sp,
                            fontWeight = FontWeight.Black,
                            color = Color.White
                        )
                    }
                    Spacer(modifier = Modifier.height(16.dp))
                    // Score bar
                    LinearProgressIndicator(
                        progress = { if (score < 0) 0f else (score / 100.0).toFloat().coerceIn(0f, 1f) },
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(6.dp)
                            .clip(RoundedCornerShape(3.dp)),
                        color = Color.White,
                        trackColor = Color.White.copy(alpha = 0.25f)
                    )
                    Spacer(modifier = Modifier.height(16.dp))
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

        // ── Status chip ──
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
                    text = if (sourceType == "audio") "🎧 Audio" else "💬 Chat",
                    color = MaterialTheme.colorScheme.primary
                )
            }
        }

        // ── Audio Player ──
        val audioUrl = evaluation.optString("audio_url", "")
        if (audioUrl.isNotEmpty()) {
            Spacer(modifier = Modifier.height(16.dp))
            AudioPlayer(url = audioUrl, token = token)
        }

        // ── AI Summary ──
        val summary = evaluation.optString("summary", "")
        if (summary.isNotEmpty()) {
            Spacer(modifier = Modifier.height(20.dp))
            SectionHeaderIcon(icon = Icons.Default.AutoAwesome, title = "Resumen IA")
            Spacer(modifier = Modifier.height(8.dp))
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(
                    containerColor = MaterialTheme.colorScheme.primary.copy(alpha = 0.06f)
                )
            ) {
                Text(
                    text = summary.replace("**", "").replace("###", "").replace("##", "").replace("#", "").trim(),
                    modifier = Modifier.padding(16.dp),
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onBackground,
                    lineHeight = 22.sp
                )
            }
        }

        // ── Criteria Items ──
        val items = evaluation.optJSONArray("items")
        if (items != null && items.length() > 0) {
            Spacer(modifier = Modifier.height(24.dp))
            SectionHeaderIcon(icon = Icons.Default.Checklist, title = "Criterios de Evaluación")
            Spacer(modifier = Modifier.height(8.dp))
            EvaluationCriteriaList(items = items)
        }

        // ── Conversation Transcript ──
        val turns = evaluation.optJSONArray("conversation_turns")
        if (turns != null && turns.length() > 0) {
            Spacer(modifier = Modifier.height(24.dp))
            SectionHeaderIcon(icon = Icons.Default.Chat, title = "Transcripción")
            Spacer(modifier = Modifier.height(8.dp))
            ChatTranscript(turns = turns)
        }

        // ── Feedback Indicators ──
        val indicators = evaluation.optJSONObject("feedback_indicators")
        if (indicators != null) {
            val hasAny = indicators.keys().asSequence().any { key ->
                val v = indicators.opt(key)
                v != null && v.toString() != "null" && v.toString().isNotEmpty()
            }
            if (hasAny) {
                Spacer(modifier = Modifier.height(24.dp))
                SectionHeaderIcon(icon = Icons.Default.Insights, title = "Indicadores de Calidad")
                Spacer(modifier = Modifier.height(8.dp))
                FeedbackIndicatorsCard(indicators)
            }
        }

        // ── Agent Response (if already responded) ──
        val response = evaluation.optJSONObject("feedback_response") ?: JSONObject()
        val hasResponded = response.optBoolean("responded")

        if (hasResponded) {
            Spacer(modifier = Modifier.height(24.dp))
            SectionHeaderIcon(
                icon = if (response.optString("type") == "accept") Icons.Default.CheckCircle else Icons.Default.Report,
                title = "Respuesta del Asesor"
            )
            Spacer(modifier = Modifier.height(8.dp))
            AgentResponseCard(response)
        }

        // ── Accept/Dispute Form (only if not responded AND status is published) ──
        if (!hasResponded && !showSuccess && evaluation.optString("status") == "published_to_agent") {
            Spacer(modifier = Modifier.height(24.dp))
            SectionHeaderIcon(icon = Icons.Default.RateReview, title = "Tu Respuesta")
            Spacer(modifier = Modifier.height(8.dp))
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
            ) {
                Column(modifier = Modifier.padding(20.dp)) {
                    Text(
                        text = "Revisa la evaluación y selecciona tu respuesta. Se refleja también en la web.",
                        fontSize = 13.sp,
                        color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.6f),
                        lineHeight = 18.sp
                    )
                    Spacer(modifier = Modifier.height(16.dp))
                    OutlinedTextField(
                        value = comment,
                        onValueChange = { comment = it },
                        label = { Text("Comentario o motivo de disputa") },
                        modifier = Modifier.fillMaxWidth(),
                        minLines = 3,
                        shape = RoundedCornerShape(12.dp),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = MaterialTheme.colorScheme.primary,
                            unfocusedBorderColor = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.15f)
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
                                    feedbackMessage = "Agrega un comentario de compromiso."
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
                                        feedbackMessage = "Error: ${e.message}"
                                    } finally {
                                        isSubmitting = false
                                    }
                                }
                            },
                            modifier = Modifier.weight(1f).height(48.dp),
                            shape = RoundedCornerShape(12.dp),
                            enabled = !isSubmitting,
                            colors = ButtonDefaults.buttonColors(
                                containerColor = Color(0xFF059669)
                            )
                        ) {
                            if (isSubmitting) {
                                CircularProgressIndicator(
                                    modifier = Modifier.size(20.dp),
                                    color = Color.White,
                                    strokeWidth = 2.dp
                                )
                            } else {
                                Icon(Icons.Default.Check, contentDescription = null, modifier = Modifier.size(18.dp))
                                Spacer(modifier = Modifier.width(6.dp))
                                Text("Aceptar", fontWeight = FontWeight.Bold, fontSize = 14.sp)
                            }
                        }
                        OutlinedButton(
                            onClick = {
                                if (comment.isBlank()) {
                                    feedbackMessage = "Agrega un motivo para disputar."
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
                                        feedbackMessage = "Error: ${e.message}"
                                    } finally {
                                        isSubmitting = false
                                    }
                                }
                            },
                            modifier = Modifier.weight(1f).height(48.dp),
                            shape = RoundedCornerShape(12.dp),
                            enabled = !isSubmitting,
                            colors = ButtonDefaults.outlinedButtonColors(
                                contentColor = Rose
                            ),
                            border = BorderStroke(1.dp, Rose.copy(alpha = 0.5f))
                        ) {
                            Icon(Icons.Default.Flag, contentDescription = null, modifier = Modifier.size(18.dp))
                            Spacer(modifier = Modifier.width(6.dp))
                            Text("Disputar", fontWeight = FontWeight.Bold, fontSize = 14.sp)
                        }
                    }
                    if (feedbackMessage != null) {
                        Spacer(modifier = Modifier.height(12.dp))
                        Text(
                            feedbackMessage!!,
                            color = MaterialTheme.colorScheme.error,
                            fontSize = 13.sp
                        )
                    }
                }
            }
        }

        // Success state
        if (showSuccess) {
            Spacer(modifier = Modifier.height(24.dp))
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(containerColor = Color(0xFF059669).copy(alpha = 0.1f))
            ) {
                Row(
                    modifier = Modifier.padding(20.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    Icon(
                        Icons.Default.CheckCircle,
                        contentDescription = null,
                        tint = Color(0xFF059669),
                        modifier = Modifier.size(28.dp)
                    )
                    Text(
                        "Respuesta registrada exitosamente.",
                        fontWeight = FontWeight.SemiBold,
                        color = Color(0xFF059669),
                        fontSize = 15.sp
                    )
                }
            }
        }

        Spacer(modifier = Modifier.height(32.dp))
    }
}

// ─── Evaluation Sub-Components ───

@Composable
fun EvalInfoPill(icon: androidx.compose.ui.graphics.vector.ImageVector, text: String) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(6.dp)
    ) {
        Icon(icon, contentDescription = null, tint = Color.White.copy(alpha = 0.8f), modifier = Modifier.size(14.dp))
        Text(text = text, fontSize = 12.sp, color = Color.White.copy(alpha = 0.9f), fontWeight = FontWeight.Medium, maxLines = 1, overflow = TextOverflow.Ellipsis)
    }
}

@Composable
fun StatusChip(label: String, status: String) {
    val chipColor = when (status) {
        "published_to_agent" -> Color(0xFF3B82F6)
        "agent_accepted" -> Color(0xFF059669)
        "agent_disputed" -> Rose
        "dispute_resolved" -> Violet
        "closed" -> Color.Gray
        else -> Amber
    }
    Box(
        modifier = Modifier
            .clip(RoundedCornerShape(20.dp))
            .background(chipColor.copy(alpha = 0.12f))
            .padding(horizontal = 14.dp, vertical = 6.dp)
    ) {
        Text(text = label, fontSize = 12.sp, fontWeight = FontWeight.Bold, color = chipColor)
    }
}

@Composable
fun SectionHeaderIcon(icon: androidx.compose.ui.graphics.vector.ImageVector, title: String) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        Icon(
            icon,
            contentDescription = null,
            tint = MaterialTheme.colorScheme.primary,
            modifier = Modifier.size(22.dp)
        )
        Text(
            text = title,
            fontSize = 18.sp,
            fontWeight = FontWeight.Bold,
            color = MaterialTheme.colorScheme.onBackground
        )
    }
}

@Composable
fun EvaluationCriteriaList(items: JSONArray) {
    // Group items by attribute name
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
                .padding(vertical = 6.dp),
            shape = RoundedCornerShape(16.dp),
            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
            elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
        ) {
            Column(modifier = Modifier.padding(16.dp)) {
                // Attribute header
                Text(
                    text = attributeName,
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.primary
                )
                Spacer(modifier = Modifier.height(12.dp))

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
                                // Status icon
                                val statusIcon = when (status) {
                                    "cumple" -> Icons.Default.CheckCircle
                                    "no_cumple" -> Icons.Default.Cancel
                                    else -> Icons.Default.HelpOutline
                                }
                                val statusColor = when (status) {
                                    "cumple" -> Color(0xFF059669)
                                    "no_cumple" -> Rose
                                    else -> Color.Gray
                                }
                                Icon(
                                    statusIcon,
                                    contentDescription = null,
                                    tint = statusColor,
                                    modifier = Modifier.size(20.dp)
                                )
                                Column(modifier = Modifier.weight(1f)) {
                                    Text(
                                        text = subName,
                                        fontSize = 14.sp,
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

                        // Expanded detail
                        if (expanded) {
                            Spacer(modifier = Modifier.height(10.dp))
                            if (evidence.isNotEmpty() && evidence != "null") {
                                Box(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .clip(RoundedCornerShape(10.dp))
                                        .background(MaterialTheme.colorScheme.primary.copy(alpha = 0.05f))
                                        .padding(12.dp)
                                ) {
                                    Column {
                                        Text(
                                            "Evidencia",
                                            fontSize = 11.sp,
                                            fontWeight = FontWeight.Bold,
                                            color = MaterialTheme.colorScheme.primary,
                                            letterSpacing = 0.5.sp
                                        )
                                        Spacer(modifier = Modifier.height(4.dp))
                                        Text(
                                            text = "\"$evidence\"",
                                            fontSize = 13.sp,
                                            fontStyle = FontStyle.Italic,
                                            color = MaterialTheme.colorScheme.onBackground.copy(alpha = 0.8f),
                                            lineHeight = 18.sp
                                        )
                                    }
                                }
                            }
                            if (notes.isNotEmpty() && notes != "null") {
                                Spacer(modifier = Modifier.height(8.dp))
                                Text(
                                    text = "📝 $notes",
                                    fontSize = 13.sp,
                                    color = MaterialTheme.colorScheme.onBackground.copy(alpha = 0.7f),
                                    lineHeight = 18.sp
                                )
                            }
                            if (confidence.isNotEmpty() && confidence != "null") {
                                Spacer(modifier = Modifier.height(4.dp))
                                Text(
                                    text = "Confianza: $confidence",
                                    fontSize = 11.sp,
                                    color = MaterialTheme.colorScheme.onBackground.copy(alpha = 0.4f)
                                )
                            }
                        }

                        if (index < criteriaItems.size - 1) {
                            Divider(
                                modifier = Modifier.padding(vertical = 10.dp),
                                color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.06f)
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
        "no_cumple" -> "No Cumple" to Rose
        "no_encontrado" -> "No Encontrado" to Amber
        else -> status.replace("_", " ").replaceFirstChar { it.uppercase() } to Color.Gray
    }
    Box(
        modifier = Modifier
            .clip(RoundedCornerShape(8.dp))
            .background(color.copy(alpha = 0.12f))
            .padding(horizontal = 10.dp, vertical = 4.dp)
    ) {
        Text(text = label, fontSize = 11.sp, fontWeight = FontWeight.Bold, color = color)
    }
}

@Composable
fun FeedbackIndicatorsCard(indicators: JSONObject) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
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
                        modifier = Modifier.fillMaxWidth().padding(vertical = 6.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(label, fontSize = 14.sp, color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.7f))
                        val tagColor = when (value.lowercase()) {
                            "alto", "high", "bueno", "good", "si", "sí", "true" -> Color(0xFF059669)
                            "medio", "medium", "regular" -> Amber
                            "bajo", "low", "malo", "bad", "critico", "critical" -> Rose
                            "no", "false" -> Rose
                            else -> MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f)
                        }
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(8.dp))
                                .background(tagColor.copy(alpha = 0.12f))
                                .padding(horizontal = 10.dp, vertical = 3.dp)
                        ) {
                            Text(
                                text = value.replaceFirstChar { it.uppercase() },
                                fontSize = 12.sp,
                                fontWeight = FontWeight.SemiBold,
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
    val bgColor = if (isAccept) Color(0xFF059669) else Rose

    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = bgColor.copy(alpha = 0.08f)),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp)
    ) {
        Column(modifier = Modifier.padding(20.dp)) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(10.dp)
            ) {
                Icon(
                    if (isAccept) Icons.Default.CheckCircle else Icons.Default.Report,
                    contentDescription = null,
                    tint = bgColor,
                    modifier = Modifier.size(24.dp)
                )
                Text(
                    text = if (isAccept) "Aceptado por el asesor" else "Disputado por el asesor",
                    fontSize = 16.sp,
                    fontWeight = FontWeight.Bold,
                    color = bgColor
                )
            }
            val commentText = if (isAccept)
                response.optString("commitment_comment", "")
            else
                response.optString("dispute_reason", "")

            if (commentText.isNotEmpty() && commentText != "null") {
                Spacer(modifier = Modifier.height(12.dp))
                Text(
                    text = commentText,
                    fontSize = 14.sp,
                    color = MaterialTheme.colorScheme.onBackground,
                    lineHeight = 20.sp
                )
            }
            val respondedAt = response.optString("responded_at", "")
            if (respondedAt.isNotEmpty() && respondedAt != "null") {
                Spacer(modifier = Modifier.height(8.dp))
                Text(
                    text = "Respondido: ${respondedAt.take(10)}",
                    fontSize = 12.sp,
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
        // Hero info card
        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(16.dp),
            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
            elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
        ) {
            Column(modifier = Modifier.padding(20.dp)) {
                Text(
                    text = transcript.optString("file_name", "Interacción"),
                    fontSize = 14.sp,
                    fontWeight = FontWeight.SemiBold,
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

        Spacer(modifier = Modifier.height(24.dp))
        SectionHeaderIcon(icon = Icons.Default.Chat, title = "Conversación")
        Spacer(modifier = Modifier.height(8.dp))

        val turns = transcript.optJSONArray("conversation_turns")
        if (turns != null && turns.length() > 0) {
            ChatTranscript(turns = turns)
        } else {
            val text = transcript.optString("transcript_text", transcript.optString("transcript_excerpt", "Transcripción no disponible."))
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
            ) {
                Text(
                    text = text,
                    style = MaterialTheme.typography.bodyMedium,
                    modifier = Modifier.padding(16.dp),
                    lineHeight = 20.sp
                )
            }
        }

        Spacer(modifier = Modifier.height(32.dp))
    }
}

@Composable
fun InfoColumn(label: String, value: String) {
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        Text(label, fontSize = 11.sp, color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f))
        Spacer(modifier = Modifier.height(2.dp))
        Text(value, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onSurface, maxLines = 1, overflow = TextOverflow.Ellipsis)
    }
}

// ─────────────────────────────────────────────────────────────────────
// CHAT TRANSCRIPT — Premium WhatsApp-style bubbles
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
                    // Right-aligned indigo bubble
                    Column(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalAlignment = Alignment.End
                    ) {
                        Text(
                            text = "$label  ${if (timestamp.isNotEmpty()) timestamp else ""}".trim(),
                            fontSize = 11.sp,
                            color = MaterialTheme.colorScheme.onBackground.copy(alpha = 0.45f),
                            modifier = Modifier.padding(end = 6.dp, bottom = 3.dp)
                        )
                        Box(
                            modifier = Modifier
                                .widthIn(max = 290.dp)
                                .clip(RoundedCornerShape(18.dp, 4.dp, 18.dp, 18.dp))
                                .background(
                                    Brush.linearGradient(
                                        colors = listOf(Color(0xFF6366F1), Color(0xFF818CF8))
                                    )
                                )
                                .padding(horizontal = 16.dp, vertical = 12.dp)
                        ) {
                            Text(
                                text = message,
                                color = Color.White,
                                fontSize = 14.sp,
                                lineHeight = 20.sp
                            )
                        }
                    }
                }
                "agent" -> {
                    // Left-aligned surface bubble
                    Column(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalAlignment = Alignment.Start
                    ) {
                        Text(
                            text = "${if (timestamp.isNotEmpty()) timestamp else ""}  $label".trim(),
                            fontSize = 11.sp,
                            color = MaterialTheme.colorScheme.onBackground.copy(alpha = 0.45f),
                            modifier = Modifier.padding(start = 6.dp, bottom = 3.dp)
                        )
                        Box(
                            modifier = Modifier
                                .widthIn(max = 290.dp)
                                .clip(RoundedCornerShape(4.dp, 18.dp, 18.dp, 18.dp))
                                .background(MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.6f))
                                .padding(horizontal = 16.dp, vertical = 12.dp)
                        ) {
                            Text(
                                text = message,
                                color = MaterialTheme.colorScheme.onSurface,
                                fontSize = 14.sp,
                                lineHeight = 20.sp
                            )
                        }
                    }
                }
                else -> {
                    // System / context — center pill
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(vertical = 4.dp),
                        contentAlignment = Alignment.Center
                    ) {
                        Box(
                            modifier = Modifier
                                .widthIn(max = 300.dp)
                                .clip(RoundedCornerShape(12.dp))
                                .background(MaterialTheme.colorScheme.surfaceVariant.copy(alpha = 0.3f))
                                .padding(horizontal = 14.dp, vertical = 8.dp),
                            contentAlignment = Alignment.Center
                        ) {
                            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                                Text(
                                    text = label.uppercase(),
                                    fontSize = 10.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.5f),
                                    letterSpacing = 0.8.sp
                                )
                                Spacer(modifier = Modifier.height(2.dp))
                                Text(
                                    text = message,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant.copy(alpha = 0.8f),
                                    fontSize = 12.sp
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
        // Hero card
        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(20.dp),
            colors = CardDefaults.cardColors(containerColor = Color.Transparent)
        ) {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(
                        brush = Brush.linearGradient(
                            colors = listOf(Color(0xFF6366F1), Color(0xFF818CF8))
                        ),
                        shape = RoundedCornerShape(20.dp)
                    )
                    .padding(24.dp)
            ) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column {
                        Text(
                            text = campaign.optString("name", "Campaña"),
                            fontSize = 20.sp,
                            fontWeight = FontWeight.Bold,
                            color = Color.White
                        )
                        Spacer(modifier = Modifier.height(4.dp))
                        Text(
                            text = if (campaign.optBoolean("active")) "🟢 Activa" else "⚫ Inactiva",
                            fontSize = 14.sp,
                            color = Color.White.copy(alpha = 0.8f)
                        )
                    }
                    Text(
                        text = campaign.optString("score_label", "0%"),
                        fontSize = 36.sp,
                        fontWeight = FontWeight.Black,
                        color = Color.White
                    )
                }
            }
        }

        Spacer(modifier = Modifier.height(16.dp))

        Card(
            modifier = Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(16.dp),
            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
            elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
        ) {
            Column(modifier = Modifier.padding(16.dp)) {
                ProgressLine("Calidad Promedio", campaign.optDouble("average_score", 0.0), getScoreColor(campaign.optDouble("average_score", 0.0)))
                Spacer(modifier = Modifier.height(16.dp))
                InfoRow("Evaluaciones", campaign.optString("evaluations", "0"))
                InfoRow("Interacciones", campaign.optString("interactions", "0"))
                val target = campaign.optDouble("target_quality", -1.0)
                if (target > 0) {
                    InfoRow("Meta de Calidad", String.format("%.1f%%", target))
                }
            }
        }

        Spacer(modifier = Modifier.height(32.dp))
    }
}
