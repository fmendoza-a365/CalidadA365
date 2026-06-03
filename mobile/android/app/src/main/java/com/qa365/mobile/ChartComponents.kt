package com.qa365.mobile

import androidx.compose.animation.core.*
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.layout.*
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import kotlin.math.max

data class ChartPoint(val label: String, val value: Double, val color: Color)

@Composable
fun TrendLineChart(points: List<ChartPoint>, modifier: Modifier = Modifier) {
    if (points.isEmpty()) return

    val animationProgress = remember { Animatable(0f) }
    LaunchedEffect(points) {
        animationProgress.animateTo(
            targetValue = 1f,
            animationSpec = tween(durationMillis = 1500, easing = FastOutSlowInEasing)
        )
    }

    val primaryColor = MaterialTheme.colorScheme.primary
    val surfaceColor = MaterialTheme.colorScheme.surface
    val gradientBrush = Brush.verticalGradient(
        colors = listOf(primaryColor.copy(alpha = 0.4f), Color.Transparent)
    )

    Column(modifier = modifier) {
        Canvas(modifier = Modifier.fillMaxWidth().height(200.dp).padding(vertical = 16.dp)) {
            val width = size.width
            val height = size.height
            val maxVal = max(100.0, points.maxOfOrNull { it.value } ?: 100.0)

            val pointOffsets = points.mapIndexed { index, point ->
                val x = if (points.size > 1) (width / (points.size - 1)) * index else width / 2
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

            // Draw Gradient Fill
            val fillPath = Path().apply {
                addPath(path)
                if (pointOffsets.isNotEmpty()) {
                    lineTo(pointOffsets.last().x, height)
                    lineTo(pointOffsets.first().x, height)
                    close()
                }
            }

            drawPath(
                path = fillPath,
                brush = gradientBrush,
                alpha = animationProgress.value
            )

            // Draw Line
            drawPath(
                path = path,
                color = primaryColor,
                style = Stroke(width = 4.dp.toPx(), cap = StrokeCap.Round),
                alpha = animationProgress.value
            )

            // Draw Points
            pointOffsets.forEachIndexed { index, offset ->
                if (animationProgress.value > (index.toFloat() / points.size)) {
                    drawCircle(
                        color = surfaceColor,
                        radius = 6.dp.toPx(),
                        center = offset
                    )
                    drawCircle(
                        color = points[index].color,
                        radius = 4.dp.toPx(),
                        center = offset
                    )
                }
            }
        }
        
        // Labels
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            points.forEach { point ->
                Text(
                    text = point.label,
                    fontSize = 10.sp,
                    color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.6f)
                )
            }
        }
    }
}

@Composable
fun TopDefectsBarChart(defects: List<ChartPoint>, modifier: Modifier = Modifier) {
    if (defects.isEmpty()) return

    val animationProgress = remember { Animatable(0f) }
    LaunchedEffect(defects) {
        animationProgress.animateTo(
            targetValue = 1f,
            animationSpec = tween(durationMillis = 1000, easing = EaseOutExpo)
        )
    }

    Column(modifier = modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(16.dp)) {
        val maxVal = max(1.0, defects.maxOfOrNull { it.value } ?: 1.0)
        
        defects.forEach { defect ->
            val percent = (defect.value / maxVal).toFloat()
            Column(modifier = Modifier.fillMaxWidth()) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    Text(text = defect.label, fontSize = 13.sp, fontWeight = FontWeight.Medium, color = MaterialTheme.colorScheme.onSurface)
                    Text(text = defect.value.toInt().toString(), fontSize = 13.sp, fontWeight = FontWeight.Bold, color = defect.color)
                }
                Spacer(modifier = Modifier.height(6.dp))
                Canvas(modifier = Modifier.fillMaxWidth().height(10.dp)) {
                    val targetWidth = size.width * percent
                    val animatedWidth = targetWidth * animationProgress.value
                    
                    // Track
                    drawRoundRect(
                        color = Color.Gray.copy(alpha = 0.1f),
                        size = Size(size.width, size.height),
                        cornerRadius = CornerRadius(size.height / 2, size.height / 2)
                    )
                    
                    // Fill
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
