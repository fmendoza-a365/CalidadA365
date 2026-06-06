package com.qa365.mobile

import android.graphics.Paint
import android.graphics.Typeface
import androidx.compose.animation.core.*
import androidx.compose.foundation.background
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.nativeCanvas
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import kotlin.math.max
import kotlin.math.roundToInt

data class ChartPoint(val label: String, val value: Double, val color: Color)
data class ComboChartPoint(
    val label: String,
    val barValue: Double,
    val lineValue: Double,
    val color: Color,
    val insight: String = ""
)

@Composable
fun TrendLineChart(
    points: List<ChartPoint>,
    modifier: Modifier = Modifier,
    valueSuffix: String = "%",
    maxValue: Double? = 100.0
) {
    if (points.isEmpty()) return

    val animationProgress = remember { Animatable(0f) }
    LaunchedEffect(points) {
        animationProgress.animateTo(
            targetValue = 1f,
            animationSpec = tween(durationMillis = 1200, easing = FastOutSlowInEasing)
        )
    }

    val primaryColor = MaterialTheme.colorScheme.primary
    val surfaceColor = MaterialTheme.colorScheme.surface
    val outlineColor = MaterialTheme.colorScheme.outline.copy(alpha = 0.3f)
    val gradientBrush = Brush.verticalGradient(
        colors = listOf(primaryColor.copy(alpha = 0.3f), Color.Transparent)
    )

    Column(modifier = modifier) {
        Canvas(modifier = Modifier.fillMaxWidth().height(180.dp).padding(vertical = 8.dp)) {
            val width = size.width
            val height = size.height
            val paddingLeft = 12.dp.toPx()
            val paddingRight = 32.dp.toPx()
            val chartWidth = width - paddingLeft - paddingRight
            val maxVal = max(1.0, maxValue ?: ((points.maxOfOrNull { it.value } ?: 1.0) * 1.2))

            // Draw horizontal dashed grid lines (25%, 50%, 75%, 100%)
            val gridLevels = listOf(0.25f, 0.5f, 0.75f, 1.0f)
            gridLevels.forEach { level ->
                val y = height - (level * height)
                drawLine(
                    color = outlineColor,
                    start = Offset(paddingLeft, y),
                    end = Offset(width - paddingRight, y),
                    strokeWidth = 1.dp.toPx(),
                    pathEffect = PathEffect.dashPathEffect(floatArrayOf(10f, 10f), 0f)
                )

                // Draw Y-axis percentage labels
                drawContext.canvas.nativeCanvas.drawText(
                    "${(level * 100).roundToInt()}%",
                    width - paddingRight + 4.dp.toPx(),
                    y + 3.dp.toPx(),
                    Paint(Paint.ANTI_ALIAS_FLAG).apply {
                        color = primaryColor.toArgb()
                        textSize = 8.sp.toPx()
                        textAlign = Paint.Align.LEFT
                        typeface = Typeface.create(Typeface.DEFAULT, Typeface.NORMAL)
                    }
                )
            }

            val pointOffsets = points.mapIndexed { index, point ->
                val x = paddingLeft + (if (points.size > 1) (chartWidth / (points.size - 1)) * index else chartWidth / 2)
                val y = height - ((point.value / maxVal) * height).toFloat()
                Offset(x, y)
            }

            val path = Path().apply {
                if (pointOffsets.isNotEmpty()) {
                    moveTo(pointOffsets.first().x, pointOffsets.first().y)
                    for (i in 0 until pointOffsets.size - 1) {
                        val p1 = pointOffsets[i]
                        val p2 = pointOffsets[i + 1]
                        val controlX = (p1.x + p2.x) / 2
                        cubicTo(controlX, p1.y, controlX, p2.y, p2.x, p2.y)
                    }
                }
            }

            // Draw Gradient Fill under the curve
            if (pointOffsets.isNotEmpty()) {
                val fillPath = Path().apply {
                    addPath(path)
                    lineTo(pointOffsets.last().x, height)
                    lineTo(pointOffsets.first().x, height)
                    close()
                }
                drawPath(
                    path = fillPath,
                    brush = gradientBrush,
                    alpha = animationProgress.value
                )
            }

            // Draw primary smoothed trend line
            drawPath(
                path = path,
                color = primaryColor,
                style = Stroke(width = 3.dp.toPx(), cap = StrokeCap.Round),
                alpha = animationProgress.value
            )

            // Draw circular nodes
            pointOffsets.forEachIndexed { index, offset ->
                if (animationProgress.value > (index.toFloat() / points.size)) {
                    drawCircle(
                        color = surfaceColor,
                        radius = 5.dp.toPx(),
                        center = offset
                    )
                    drawCircle(
                        color = points[index].color,
                        radius = 3.5.dp.toPx(),
                        center = offset
                    )
                }
            }

            val labelPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
                color = primaryColor.toArgb()
                textAlign = Paint.Align.CENTER
                textSize = 11.sp.toPx()
                typeface = Typeface.create(Typeface.DEFAULT, Typeface.BOLD)
            }

            pointOffsets.forEachIndexed { index, offset ->
                if (animationProgress.value > (index.toFloat() / points.size)) {
                    drawContext.canvas.nativeCanvas.drawText(
                        "${points[index].value.roundToInt()}$valueSuffix",
                        offset.x,
                        (offset.y - 10.dp.toPx()).coerceAtLeast(12.dp.toPx()),
                        labelPaint
                    )
                }
            }
        }
        
        Spacer(modifier = Modifier.height(6.dp))

        // Label row
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(start = 12.dp, end = 32.dp),
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            points.forEach { point ->
                Text(
                    text = point.label,
                    fontSize = 10.sp,
                    fontWeight = FontWeight.Medium,
                    color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.5f)
                )
            }
        }
    }
}

@Composable
fun ComboBarLineChart(
    points: List<ComboChartPoint>,
    modifier: Modifier = Modifier,
    barLabel: String = "Cantidad",
    lineLabel: String = "%",
    lineSuffix: String = "%",
    lineMaxValue: Double = 100.0
) {
    if (points.isEmpty()) return

    val animationProgress = remember { Animatable(0f) }
    LaunchedEffect(points) {
        animationProgress.snapTo(0f)
        animationProgress.animateTo(
            targetValue = 1f,
            animationSpec = tween(durationMillis = 850, easing = FastOutSlowInEasing)
        )
    }

    val primaryColor = MaterialTheme.colorScheme.primary
    val barColor = points.firstOrNull()?.color ?: primaryColor
    val surfaceColor = MaterialTheme.colorScheme.surface
    val outlineColor = MaterialTheme.colorScheme.outline.copy(alpha = 0.22f)
    val mutedText = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.58f)

    Column(modifier = modifier) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(10.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            ChartLegendDot(color = barColor, label = barLabel)
            ChartLegendDot(color = primaryColor, label = lineLabel)
        }

        Spacer(modifier = Modifier.height(8.dp))

        Canvas(modifier = Modifier.fillMaxWidth().height(190.dp).padding(vertical = 8.dp)) {
            val width = size.width
            val height = size.height
            val paddingLeft = 12.dp.toPx()
            val paddingRight = 32.dp.toPx()
            val chartWidth = width - paddingLeft - paddingRight
            val maxBar = max(1.0, points.maxOfOrNull { it.barValue } ?: 1.0)
            val maxLine = max(1.0, lineMaxValue)
            val step = chartWidth / points.size
            val barWidth = (step * 0.46f).coerceIn(8.dp.toPx(), 24.dp.toPx())

            listOf(0.25f, 0.5f, 0.75f, 1.0f).forEach { level ->
                val y = height - (level * height)
                drawLine(
                    color = outlineColor,
                    start = Offset(paddingLeft, y),
                    end = Offset(width - paddingRight, y),
                    strokeWidth = 1.dp.toPx(),
                    pathEffect = PathEffect.dashPathEffect(floatArrayOf(8f, 8f), 0f)
                )

                // Draw Y-axis percentage labels
                drawContext.canvas.nativeCanvas.drawText(
                    "${(level * 100).roundToInt()}%",
                    width - paddingRight + 4.dp.toPx(),
                    y + 3.dp.toPx(),
                    Paint(Paint.ANTI_ALIAS_FLAG).apply {
                        color = mutedText.toArgb()
                        textSize = 8.sp.toPx()
                        textAlign = Paint.Align.LEFT
                        typeface = Typeface.create(Typeface.DEFAULT, Typeface.NORMAL)
                    }
                )
            }

            val lineOffsets = mutableListOf<Offset>()

            points.forEachIndexed { index, point ->
                val centerX = paddingLeft + step * index + step / 2
                val barHeight = ((point.barValue / maxBar) * height).toFloat() * animationProgress.value
                val top = height - barHeight

                drawRoundRect(
                    color = barColor.copy(alpha = 0.20f),
                    topLeft = Offset(centerX - barWidth / 2, 0f),
                    size = Size(barWidth, height),
                    cornerRadius = CornerRadius(8.dp.toPx(), 8.dp.toPx())
                )
                drawRoundRect(
                    color = barColor,
                    topLeft = Offset(centerX - barWidth / 2, top),
                    size = Size(barWidth, barHeight),
                    cornerRadius = CornerRadius(8.dp.toPx(), 8.dp.toPx())
                )

                // Draw bar count value directly
                if (point.barValue > 0) {
                    val barLabelPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
                        textAlign = Paint.Align.CENTER
                        textSize = 9.sp.toPx()
                        typeface = Typeface.create(Typeface.DEFAULT, Typeface.NORMAL)
                    }
                    val textY = if (top > 20.dp.toPx()) top - 4.dp.toPx() else top + 12.dp.toPx()
                    val textColor = if (top > 20.dp.toPx()) mutedText.toArgb() else Color.White.toArgb()
                    barLabelPaint.color = textColor
                    
                    drawContext.canvas.nativeCanvas.drawText(
                        point.barValue.roundToInt().toString(),
                        centerX,
                        textY,
                        barLabelPaint
                    )
                }

                val y = height - ((point.lineValue / maxLine) * height).toFloat().coerceIn(0f, height)
                lineOffsets.add(Offset(centerX, y))
            }

            val path = Path().apply {
                if (lineOffsets.isNotEmpty()) {
                    moveTo(lineOffsets.first().x, lineOffsets.first().y)
                    for (i in 0 until lineOffsets.size - 1) {
                        val p1 = lineOffsets[i]
                        val p2 = lineOffsets[i + 1]
                        val controlX = (p1.x + p2.x) / 2
                        cubicTo(controlX, p1.y, controlX, p2.y, p2.x, p2.y)
                    }
                }
            }

            drawPath(
                path = path,
                color = primaryColor,
                style = Stroke(width = 3.dp.toPx(), cap = StrokeCap.Round),
                alpha = animationProgress.value
            )

            lineOffsets.forEachIndexed { index, offset ->
                drawCircle(color = surfaceColor, radius = 5.dp.toPx(), center = offset)
                drawCircle(color = primaryColor, radius = 3.5.dp.toPx(), center = offset)

                val labelPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
                    color = primaryColor.toArgb()
                    textAlign = Paint.Align.CENTER
                    textSize = 10.sp.toPx()
                    typeface = Typeface.create(Typeface.DEFAULT, Typeface.BOLD)
                }
                drawContext.canvas.nativeCanvas.drawText(
                    "${points[index].lineValue.roundToInt()}$lineSuffix",
                    offset.x,
                    (offset.y - 10.dp.toPx()).coerceAtLeast(12.dp.toPx()),
                    labelPaint
                )
            }
        }

        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(start = 12.dp, end = 32.dp),
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            points.forEach { point ->
                Text(
                    text = point.label,
                    fontSize = 10.sp,
                    fontWeight = FontWeight.Medium,
                    color = mutedText,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.widthIn(max = 64.dp)
                )
            }
        }
    }
}

@Composable
private fun ChartLegendDot(color: Color, label: String) {
    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(5.dp)) {
        Box(modifier = Modifier.size(8.dp).clip(RoundedCornerShape(99.dp)).background(color))
        Text(label, fontSize = 11.sp, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.64f))
    }
}

@Composable
fun TopDefectsBarChart(defects: List<ChartPoint>, modifier: Modifier = Modifier) {
    if (defects.isEmpty()) return

    val animationProgress = remember { Animatable(0f) }
    LaunchedEffect(defects) {
        animationProgress.animateTo(
            targetValue = 1f,
            animationSpec = tween(durationMillis = 800, easing = EaseOutExpo)
        )
    }
    val outlineColor = MaterialTheme.colorScheme.outline
    Column(modifier = modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        val maxVal = max(1.0, defects.maxOfOrNull { it.value } ?: 1.0)
        
        defects.forEach { defect ->
            val percent = (defect.value / maxVal).toFloat()
            Column(modifier = Modifier.fillMaxWidth()) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text(
                        text = defect.label,
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Medium,
                        color = MaterialTheme.colorScheme.onSurface,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                        modifier = Modifier.weight(1f)
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Text(
                        text = defect.value.toInt().toString(),
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Bold,
                        color = defect.color
                    )
                }
                Spacer(modifier = Modifier.height(4.dp))
                Canvas(modifier = Modifier.fillMaxWidth().height(8.dp)) {
                    val targetWidth = size.width * percent
                    val animatedWidth = targetWidth * animationProgress.value
                    
                    // Background Track
                    drawRoundRect(
                        color = outlineColor.copy(alpha = 0.1f),
                        size = Size(size.width, size.height),
                        cornerRadius = CornerRadius(size.height / 2, size.height / 2)
                    )
                    
                    // Animated Fill
                    drawRoundRect(
                        color = defect.color,
                        size = Size(animatedWidth, size.height),
                        cornerRadius = CornerRadius(size.height / 2, size.height / 2)
                    )
                }
            }
        }
    }
}
