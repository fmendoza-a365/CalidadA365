package com.qa365.mobile;

import android.app.Activity;
import android.content.Intent;
import android.content.SharedPreferences;
import android.graphics.Color;
import android.graphics.Typeface;
import android.graphics.drawable.GradientDrawable;
import android.net.Uri;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.text.InputType;
import android.view.Gravity;
import android.view.View;
import android.view.inputmethod.EditorInfo;
import android.widget.Button;
import android.widget.EditText;
import android.widget.HorizontalScrollView;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.ScrollView;
import android.widget.Switch;
import android.widget.TextView;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.Locale;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

public class MainActivity extends Activity {
    private static final String PREFS = "qa365_mobile";
    private static final String DEFAULT_SERVER = "https://qa365.com.pe";

    private static final int GREEN = Color.rgb(16, 185, 129);
    private static final int BLUE = Color.rgb(99, 102, 241);
    private static final int CYAN = Color.rgb(14, 165, 233);
    private static final int AMBER = Color.rgb(245, 158, 11);
    private static final int ROSE = Color.rgb(244, 63, 94);
    private static final int VIOLET = Color.rgb(139, 92, 246);

    private final Handler mainHandler = new Handler(Looper.getMainLooper());
    private final ExecutorService executor = Executors.newSingleThreadExecutor();

    private SharedPreferences prefs;
    private LinearLayout root;
    private LinearLayout content;
    private JSONObject dashboardData;
    private String token;
    private String serverUrl;
    private String activeTab = "resumen";
    private boolean darkMode;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        prefs = getSharedPreferences(PREFS, MODE_PRIVATE);
        token = prefs.getString("token", null);
        serverUrl = prefs.getString("server_url", DEFAULT_SERVER);
        darkMode = prefs.getBoolean("dark_mode", true);
        applySystemBars();

        if (token == null || token.isEmpty()) {
            showLogin();
        } else {
            showDashboard();
        }
    }

    @Override
    protected void onDestroy() {
        executor.shutdownNow();
        super.onDestroy();
    }

    private void showLogin() {
        applySystemBars();
        root = vertical();
        root.setPadding(dp(22), dp(28), dp(22), dp(22));
        root.setGravity(Gravity.CENTER_HORIZONTAL);
        root.setBackgroundColor(bg());

        root.addView(themeRow(), matchWrap());
        ImageView logo = logoView(220, 98);
        LinearLayout.LayoutParams logoParams = wrapWrap();
        logoParams.setMargins(0, dp(22), 0, dp(10));
        root.addView(logo, logoParams);

        TextView title = text("Centro movil de calidad", 22, textColor(), Typeface.BOLD);
        title.setGravity(Gravity.CENTER);
        root.addView(title, matchWrap());

        TextView subtitle = text("Seguimiento ejecutivo y resultados de asesores", 14, muted(), Typeface.NORMAL);
        subtitle.setGravity(Gravity.CENTER);
        LinearLayout.LayoutParams subtitleParams = matchWrap();
        subtitleParams.setMargins(0, dp(6), 0, dp(22));
        root.addView(subtitle, subtitleParams);

        LinearLayout card = card();
        card.setPadding(dp(16), dp(16), dp(16), dp(16));

        EditText serverInput = input("Servidor", false);
        serverInput.setText(serverUrl);
        card.addView(serverInput, matchWrap());

        EditText loginInput = input("Usuario o email", false);
        LinearLayout.LayoutParams loginParams = matchWrap();
        loginParams.setMargins(0, dp(12), 0, 0);
        card.addView(loginInput, loginParams);

        EditText passwordInput = input("Contrasena", true);
        passwordInput.setImeOptions(EditorInfo.IME_ACTION_DONE);
        LinearLayout.LayoutParams passwordParams = matchWrap();
        passwordParams.setMargins(0, dp(12), 0, 0);
        card.addView(passwordInput, passwordParams);

        Button loginButton = primaryButton("Ingresar");
        LinearLayout.LayoutParams buttonParams = matchWrap();
        buttonParams.setMargins(0, dp(16), 0, 0);
        card.addView(loginButton, buttonParams);

        TextView status = text("", 13, muted(), Typeface.NORMAL);
        LinearLayout.LayoutParams statusParams = matchWrap();
        statusParams.setMargins(0, dp(12), 0, 0);
        card.addView(status, statusParams);

        root.addView(card, matchWrap());
        setContentView(root);

        View.OnClickListener loginAction = v -> {
            String typedServer = normalizeServer(serverInput.getText().toString());
            String login = loginInput.getText().toString().trim();
            String password = passwordInput.getText().toString();

            if (typedServer.isEmpty() || login.isEmpty() || password.isEmpty()) {
                status.setText("Completa servidor, usuario y contrasena.");
                return;
            }

            status.setText("Validando credenciales...");
            loginButton.setEnabled(false);
            executor.execute(() -> {
                try {
                    serverUrl = typedServer;
                    JSONObject body = new JSONObject();
                    body.put("login", login);
                    body.put("password", password);
                    body.put("device_name", "QA365 Android");

                    JSONObject response = request("POST", "/api/mobile/login", body);
                    token = response.getString("access_token");
                    prefs.edit()
                        .putString("token", token)
                        .putString("server_url", serverUrl)
                        .apply();
                    activeTab = "resumen";
                    runOnMain(this::showDashboard);
                } catch (Exception ex) {
                    runOnMain(() -> {
                        loginButton.setEnabled(true);
                        status.setText(cleanError(ex));
                    });
                }
            });
        };

        loginButton.setOnClickListener(loginAction);
        passwordInput.setOnEditorActionListener((v, actionId, event) -> {
            if (actionId == EditorInfo.IME_ACTION_DONE) {
                loginAction.onClick(loginButton);
                return true;
            }
            return false;
        });
    }

    private void showDashboard() {
        applySystemBars();
        root = vertical();
        root.setBackgroundColor(bg());
        root.setPadding(dp(14), dp(18), dp(14), 0);

        root.addView(dashboardHeader(), matchWrap());

        ScrollView scrollView = new ScrollView(this);
        content = vertical();
        content.setPadding(0, dp(12), 0, dp(20));
        scrollView.addView(content);
        root.addView(scrollView, new LinearLayout.LayoutParams(
            LinearLayout.LayoutParams.MATCH_PARENT,
            0,
            1
        ));

        setContentView(root);
        loadDashboard();
    }

    private LinearLayout dashboardHeader() {
        LinearLayout wrapper = vertical();

        LinearLayout top = horizontal();
        top.setGravity(Gravity.CENTER_VERTICAL);
        top.addView(logoView(126, 56), wrapWrap());

        LinearLayout titleBox = vertical();
        TextView title = text("QA365", 21, textColor(), Typeface.BOLD);
        titleBox.addView(title, matchWrap());
        TextView subtitle = text("Dashboard movil", 12, muted(), Typeface.NORMAL);
        titleBox.addView(subtitle, matchWrap());
        LinearLayout.LayoutParams titleParams = new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1);
        titleParams.setMargins(dp(8), 0, 0, 0);
        top.addView(titleBox, titleParams);

        Button refreshButton = iconButton("Actualizar");
        refreshButton.setOnClickListener(v -> loadDashboard());
        top.addView(refreshButton, wrapWrap());

        Button logoutButton = iconButton("Salir");
        logoutButton.setOnClickListener(v -> logout());
        LinearLayout.LayoutParams logoutParams = wrapWrap();
        logoutParams.setMargins(dp(6), 0, 0, 0);
        top.addView(logoutButton, logoutParams);

        wrapper.addView(top, matchWrap());

        LinearLayout themeLine = themeRow();
        LinearLayout.LayoutParams themeParams = matchWrap();
        themeParams.setMargins(0, dp(8), 0, 0);
        wrapper.addView(themeLine, themeParams);

        return wrapper;
    }

    private void loadDashboard() {
        if (content == null) {
            return;
        }

        content.removeAllViews();
        LinearLayout loading = card();
        loading.setGravity(Gravity.CENTER);
        loading.setPadding(dp(16), dp(28), dp(16), dp(28));
        loading.addView(new ProgressBar(this), wrapWrap());
        TextView loadingText = text("Cargando tablero...", 14, muted(), Typeface.NORMAL);
        LinearLayout.LayoutParams loadingTextParams = wrapWrap();
        loadingTextParams.setMargins(0, dp(12), 0, 0);
        loading.addView(loadingText, loadingTextParams);
        content.addView(loading, matchWrap());

        executor.execute(() -> {
            try {
                JSONObject response = request("GET", "/api/mobile/dashboard", null);
                runOnMain(() -> {
                    dashboardData = response;
                    renderDashboard();
                });
            } catch (ApiException ex) {
                if (ex.code == 401) {
                    clearSession();
                    runOnMain(this::showLogin);
                    return;
                }
                runOnMain(() -> renderError(ex.getMessage()));
            } catch (Exception ex) {
                runOnMain(() -> renderError(cleanError(ex)));
            }
        });
    }

    private void renderDashboard() {
        content.removeAllViews();

        JSONObject profile = object(dashboardData, "profile");
        String view = profile.optString("primary_view", "executive");
        boolean isAgent = "agent".equals(view);

        content.addView(heroCard(profile, isAgent), matchWrap());
        content.addView(tabBar(), sectionParams());

        if ("alertas".equals(activeTab)) {
            renderAlerts();
        } else if ("ranking".equals(activeTab)) {
            renderRanking();
        } else if ("resultados".equals(activeTab)) {
            renderResults(isAgent);
        } else {
            renderOverview(isAgent);
        }
    }

    private LinearLayout heroCard(JSONObject profile, boolean isAgent) {
        LinearLayout hero = card();
        hero.setPadding(dp(16), dp(16), dp(16), dp(16));

        TextView scope = text(isAgent ? "Vista de asesor" : "Vista ejecutiva", 12, accent(), Typeface.BOLD);
        hero.addView(scope, matchWrap());

        TextView name = text(nonEmpty(profile.optString("name"), "Usuario QA365"), 24, textColor(), Typeface.BOLD);
        LinearLayout.LayoutParams nameParams = matchWrap();
        nameParams.setMargins(0, dp(4), 0, 0);
        hero.addView(name, nameParams);

        JSONObject summary = object(dashboardData, "summary");
        JSONObject overview = object(dashboardData, "overview");
        String detail = isAgent
            ? "Tus resultados publicados y feedback pendiente."
            : "Seguimiento de calidad, feedback y alertas operativas.";
        TextView caption = text(detail, 13, muted(), Typeface.NORMAL);
        LinearLayout.LayoutParams captionParams = matchWrap();
        captionParams.setMargins(0, dp(4), 0, dp(14));
        hero.addView(caption, captionParams);

        LinearLayout metrics = horizontal();
        metrics.addView(compactMetric("Nota", percentText(overview.opt("average_score")), GREEN), weightParams(1, 0, dp(5), 0, 0));
        metrics.addView(compactMetric("Alertas", summary.optString("open_alerts", "0"), AMBER), weightParams(1, dp(5), 0, 0, 0));
        hero.addView(metrics, matchWrap());

        return hero;
    }

    private void renderOverview(boolean isAgent) {
        JSONObject overview = object(dashboardData, "overview");
        JSONObject feedback = object(dashboardData, "feedback");
        JSONObject summary = object(dashboardData, "summary");

        if (isAgent) {
            JSONObject agent = object(dashboardData, "agent");
            JSONObject league = object(agent, "league");
            LinearLayout agentCard = section("Mi desempeno");
            agentCard.addView(bigValue("Liga", league.optString("name", "Sin liga"), league.optString("score_label", "0%"), GREEN), matchWrap());
            agentCard.addView(progressLine("Promedio personal", league.optDouble("score", 0), GREEN), spaced());
            agentCard.addView(infoRow("Evaluaciones", overview.optString("total_evaluations", "0")), spaced());
            agentCard.addView(infoRow("Feedback visto", percentText(overview.opt("feedback_percentage"))), spaced());
            content.addView(agentCard, sectionParams());
        }

        LinearLayout grid = vertical();
        grid.addView(metricRow(
            metricCard("Evaluaciones", overview.optString("total_evaluations", "0"), "Total del periodo", BLUE),
            metricCard("Nota sin MP", percentText(overview.opt("average_score_no_mp")), "Promedio limpio", CYAN)
        ));
        grid.addView(metricRow(
            metricCard("Criticas", summary.optString("critical_scores", "0"), "Score menor a 70", ROSE),
            metricCard("Feedback", percentText(feedback.opt("done_pct")), "Completado", GREEN)
        ));
        content.addView(grid, sectionParams());

        renderTrend();
        renderCampaigns();
        renderDefects();
    }

    private void renderTrend() {
        JSONArray trend = dashboardData.optJSONArray("quality_trend");
        LinearLayout section = section("Tendencia de calidad");
        if (trend == null || trend.length() == 0) {
            section.addView(emptyText("Sin datos de tendencia en el periodo."), spaced());
        } else {
            for (int i = 0; i < trend.length(); i++) {
                JSONObject point = trend.optJSONObject(i);
                if (point == null) {
                    continue;
                }
                section.addView(progressLine(
                    shortLabel(point.optString("label", "Dia")),
                    point.optDouble("avg_score", 0),
                    scoreColor(point.optDouble("avg_score", 0))
                ), spaced());
            }
        }
        content.addView(section, sectionParams());
    }

    private void renderCampaigns() {
        JSONArray campaigns = dashboardData.optJSONArray("campaigns");
        LinearLayout section = section("Campanas");
        if (campaigns == null || campaigns.length() == 0) {
            section.addView(emptyText("No hay campanas con evaluaciones visibles."), spaced());
        } else {
            for (int i = 0; i < campaigns.length(); i++) {
                JSONObject campaign = campaigns.optJSONObject(i);
                if (campaign == null) {
                    continue;
                }
                section.addView(twoLineRow(
                    nonEmpty(campaign.optString("label"), "Campana"),
                    percentText(campaign.opt("avg_score")),
                    campaign.optString("count", "0") + " evaluaciones",
                    scoreColor(campaign.optDouble("avg_score", 0))
                ), spaced());
            }
        }
        content.addView(section, sectionParams());
    }

    private void renderDefects() {
        JSONArray defects = dashboardData.optJSONArray("top_defects");
        LinearLayout section = section("Principales hallazgos");
        if (defects == null || defects.length() == 0) {
            section.addView(emptyText("Sin hallazgos registrados."), spaced());
        } else {
            for (int i = 0; i < defects.length(); i++) {
                JSONObject defect = defects.optJSONObject(i);
                if (defect == null) {
                    continue;
                }
                int color = defect.optBoolean("is_critical") ? ROSE : AMBER;
                section.addView(twoLineRow(
                    nonEmpty(defect.optString("label"), "Criterio"),
                    defect.optString("count", "0"),
                    defect.optBoolean("is_critical") ? "Critico" : "No conforme",
                    color
                ), spaced());
            }
        }
        content.addView(section, sectionParams());
    }

    private void renderAlerts() {
        JSONArray alerts = dashboardData.optJSONArray("alerts");
        LinearLayout section = section("Alertas abiertas");
        if (alerts == null || alerts.length() == 0) {
            section.addView(emptyText("No hay alertas abiertas."), spaced());
        } else {
            for (int i = 0; i < alerts.length(); i++) {
                JSONObject alert = alerts.optJSONObject(i);
                if (alert == null) {
                    continue;
                }
                int color = severityColor(alert.optString("severity", "info"));
                LinearLayout item = clickableCard(alert.optString("action_url", ""));
                item.addView(labelChip(alert.optString("severity", "INFO").toUpperCase(Locale.US), color), wrapWrap());
                item.addView(titleText(nonEmpty(alert.optString("title"), "Alerta")), spaced());
                item.addView(bodyText(nonEmpty(alert.optString("description"), "Requiere revision.")), spacedSmall());
                section.addView(item, spaced());
            }
        }
        content.addView(section, sectionParams());
    }

    private void renderRanking() {
        JSONArray ranking = dashboardData.optJSONArray("ranking");
        LinearLayout section = section("Ranking de asesores");
        if (ranking == null || ranking.length() == 0) {
            section.addView(emptyText("No hay ranking disponible."), spaced());
        } else {
            for (int i = 0; i < ranking.length(); i++) {
                JSONObject row = ranking.optJSONObject(i);
                if (row == null) {
                    continue;
                }
                double score = row.optDouble("avg_score", 0);
                section.addView(twoLineRow(
                    row.optString("position", String.valueOf(i + 1)) + ". " + nonEmpty(row.optString("label"), "Asesor"),
                    percentText(score),
                    row.optString("total_evals", "0") + " evals | " + row.optString("level", "Nivel"),
                    scoreColor(score)
                ), spaced());
            }
        }
        content.addView(section, sectionParams());
    }

    private void renderResults(boolean isAgent) {
        JSONArray results = null;
        if (isAgent) {
            results = object(dashboardData, "agent").optJSONArray("match_history");
        }
        if (results == null) {
            results = dashboardData.optJSONArray("evaluations");
        }

        LinearLayout section = section(isAgent ? "Mis resultados" : "Ultimas evaluaciones");
        if (results == null || results.length() == 0) {
            section.addView(emptyText("Sin evaluaciones visibles."), spaced());
        } else {
            for (int i = 0; i < results.length(); i++) {
                JSONObject evaluation = results.optJSONObject(i);
                if (evaluation == null) {
                    continue;
                }
                LinearLayout item = clickableCard(evaluation.optString("action_url", ""));
                LinearLayout top = horizontal();
                top.setGravity(Gravity.CENTER_VERTICAL);
                TextView name = titleText(nonEmpty(evaluation.optString("campaign"), "Sin campana"));
                top.addView(name, new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1));
                top.addView(labelChip(evaluation.optString("score_label", "Sin nota"), scoreColor(evaluation.optDouble("score", -1))));
                item.addView(top, matchWrap());

                String agent = nonEmpty(evaluation.optString("agent"), "Sin asesor");
                String status = nonEmpty(evaluation.optString("status_label"), "Sin estado");
                item.addView(bodyText(agent + " | " + status), spacedSmall());

                JSONObject indicators = evaluation.optJSONObject("feedback_indicators");
                JSONObject audio = evaluation.optJSONObject("audio");
                String risk = indicators == null ? "No detectado" : nonEmpty(indicators.optString("customer_experience_risk"), "No detectado");
                String deadAir = audio == null ? "00:00" : nonEmpty(audio.optString("dead_air_label"), "00:00");
                item.addView(bodyText("Riesgo: " + risk + " | Tiempo muerto: " + deadAir), spacedSmall());
                section.addView(item, spaced());
            }
        }
        content.addView(section, sectionParams());
    }

    private LinearLayout tabBar() {
        HorizontalScrollView scroll = new HorizontalScrollView(this);
        scroll.setHorizontalScrollBarEnabled(false);
        LinearLayout row = horizontal();
        row.setPadding(0, 0, 0, dp(2));
        row.addView(tabButton("resumen", "Resumen"), tabParams());
        row.addView(tabButton("alertas", "Alertas"), tabParams());
        row.addView(tabButton("ranking", "Ranking"), tabParams());
        row.addView(tabButton("resultados", "Resultados"), tabParams());
        scroll.addView(row);

        LinearLayout wrapper = vertical();
        wrapper.addView(scroll, matchWrap());
        return wrapper;
    }

    private Button tabButton(String key, String label) {
        Button button = new Button(this);
        boolean active = key.equals(activeTab);
        button.setText(label);
        button.setAllCaps(false);
        button.setTextSize(12);
        button.setTypeface(Typeface.DEFAULT, active ? Typeface.BOLD : Typeface.NORMAL);
        button.setTextColor(active ? Color.WHITE : muted());
        button.setBackground(rounded(active ? accent() : cardAlt(), active ? accent() : border(), 8));
        button.setMinHeight(dp(40));
        button.setPadding(dp(12), 0, dp(12), 0);
        button.setOnClickListener(v -> {
            activeTab = key;
            renderDashboard();
        });
        return button;
    }

    private LinearLayout themeRow() {
        LinearLayout row = horizontal();
        row.setGravity(Gravity.CENTER_VERTICAL);

        TextView label = text(darkMode ? "Modo oscuro" : "Modo claro", 12, muted(), Typeface.BOLD);
        row.addView(label, new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1));

        Switch themeSwitch = new Switch(this);
        themeSwitch.setChecked(darkMode);
        themeSwitch.setText(darkMode ? "Oscuro" : "Claro");
        themeSwitch.setTextColor(muted());
        themeSwitch.setTextSize(12);
        themeSwitch.setOnCheckedChangeListener((buttonView, checked) -> {
            darkMode = checked;
            prefs.edit().putBoolean("dark_mode", darkMode).apply();
            if (token == null || token.isEmpty()) {
                showLogin();
            } else {
                showDashboard();
            }
        });
        row.addView(themeSwitch, wrapWrap());
        return row;
    }

    private JSONObject request(String method, String path, JSONObject body) throws Exception {
        URL url = new URL(serverUrl + path);
        HttpURLConnection connection = (HttpURLConnection) url.openConnection();
        connection.setRequestMethod(method);
        connection.setConnectTimeout(15000);
        connection.setReadTimeout(20000);
        connection.setRequestProperty("Accept", "application/json");

        if (token != null && !token.isEmpty()) {
            connection.setRequestProperty("Authorization", "Bearer " + token);
        }

        if (body != null) {
            byte[] bytes = body.toString().getBytes(StandardCharsets.UTF_8);
            connection.setDoOutput(true);
            connection.setRequestProperty("Content-Type", "application/json; charset=utf-8");
            connection.setFixedLengthStreamingMode(bytes.length);
            try (OutputStream output = connection.getOutputStream()) {
                output.write(bytes);
            }
        }

        int code = connection.getResponseCode();
        BufferedReader reader = new BufferedReader(new InputStreamReader(
            code >= 200 && code < 300 ? connection.getInputStream() : connection.getErrorStream(),
            StandardCharsets.UTF_8
        ));
        StringBuilder response = new StringBuilder();
        String line;
        while ((line = reader.readLine()) != null) {
            response.append(line);
        }
        reader.close();
        connection.disconnect();

        JSONObject json = response.length() == 0 ? new JSONObject() : new JSONObject(response.toString());
        if (code < 200 || code >= 300) {
            throw new ApiException(code, json.optString("message", "Error del servidor"));
        }

        return json;
    }

    private void logout() {
        executor.execute(() -> {
            try {
                request("POST", "/api/mobile/logout", new JSONObject());
            } catch (Exception ignored) {
            }
            clearSession();
            runOnMain(this::showLogin);
        });
    }

    private LinearLayout metricRow(View left, View right) {
        LinearLayout row = horizontal();
        row.addView(left, weightParams(1, 0, dp(5), dp(8), 0));
        row.addView(right, weightParams(1, dp(5), 0, dp(8), 0));
        return row;
    }

    private LinearLayout metricCard(String label, String value, String detail, int color) {
        LinearLayout box = compactCard();
        box.addView(text(label, 12, muted(), Typeface.BOLD), matchWrap());
        TextView metric = text(value, 26, color, Typeface.BOLD);
        box.addView(metric, spacedSmall());
        box.addView(text(detail, 12, muted(), Typeface.NORMAL), spacedSmall());
        return box;
    }

    private LinearLayout compactMetric(String label, String value, int color) {
        LinearLayout box = compactCard();
        box.addView(text(label, 11, muted(), Typeface.BOLD), matchWrap());
        box.addView(text(value, 24, color, Typeface.BOLD), spacedSmall());
        return box;
    }

    private LinearLayout bigValue(String label, String value, String detail, int color) {
        LinearLayout box = compactCard();
        box.addView(text(label, 12, muted(), Typeface.BOLD), matchWrap());
        box.addView(text(value, 26, color, Typeface.BOLD), spacedSmall());
        box.addView(text(detail, 13, muted(), Typeface.NORMAL), spacedSmall());
        return box;
    }

    private LinearLayout progressLine(String label, double percent, int color) {
        LinearLayout box = vertical();
        LinearLayout row = horizontal();
        row.setGravity(Gravity.CENTER_VERTICAL);
        row.addView(text(label, 13, textColor(), Typeface.BOLD), new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1));
        row.addView(text(percentText(percent), 13, color, Typeface.BOLD), wrapWrap());
        box.addView(row, matchWrap());

        LinearLayout track = new LinearLayout(this);
        track.setBackground(rounded(trackColor(), trackColor(), 10));
        LinearLayout fill = new LinearLayout(this);
        fill.setBackground(rounded(color, color, 10));
        int width = Math.max(0, Math.min(100, (int) Math.round(percent)));
        track.addView(fill, new LinearLayout.LayoutParams(0, dp(8), width));
        LinearLayout.LayoutParams remainder = new LinearLayout.LayoutParams(0, dp(8), 100 - width);
        LinearLayout spacer = new LinearLayout(this);
        track.addView(spacer, remainder);
        LinearLayout.LayoutParams trackParams = matchWrap();
        trackParams.setMargins(0, dp(7), 0, 0);
        box.addView(track, trackParams);
        return box;
    }

    private LinearLayout twoLineRow(String title, String value, String detail, int color) {
        LinearLayout row = compactCard();
        LinearLayout top = horizontal();
        top.setGravity(Gravity.CENTER_VERTICAL);
        top.addView(text(title, 14, textColor(), Typeface.BOLD), new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1));
        top.addView(text(value, 15, color, Typeface.BOLD), wrapWrap());
        row.addView(top, matchWrap());
        row.addView(text(detail, 12, muted(), Typeface.NORMAL), spacedSmall());
        return row;
    }

    private LinearLayout clickableCard(String actionUrl) {
        LinearLayout item = compactCard();
        if (actionUrl != null && !actionUrl.trim().isEmpty()) {
            item.setOnClickListener(v -> openUrl(actionUrl));
        }
        return item;
    }

    private LinearLayout infoRow(String label, String value) {
        LinearLayout row = horizontal();
        row.setGravity(Gravity.CENTER_VERTICAL);
        row.addView(text(label, 13, muted(), Typeface.NORMAL), new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1));
        row.addView(text(value, 13, textColor(), Typeface.BOLD), wrapWrap());
        return row;
    }

    private TextView titleText(String value) {
        return text(value, 15, textColor(), Typeface.BOLD);
    }

    private TextView bodyText(String value) {
        return text(value, 12, muted(), Typeface.NORMAL);
    }

    private TextView emptyText(String value) {
        TextView view = text(value, 13, muted(), Typeface.NORMAL);
        view.setGravity(Gravity.CENTER);
        view.setPadding(0, dp(10), 0, dp(10));
        return view;
    }

    private TextView labelChip(String label, int color) {
        TextView chip = text(label, 12, color, Typeface.BOLD);
        chip.setPadding(dp(8), dp(4), dp(8), dp(4));
        chip.setBackground(rounded(alpha(color, 36), alpha(color, 120), 18));
        return chip;
    }

    private LinearLayout section(String titleText) {
        LinearLayout section = card();
        section.setPadding(dp(14), dp(14), dp(14), dp(14));
        section.addView(text(titleText, 17, textColor(), Typeface.BOLD), matchWrap());
        return section;
    }

    private LinearLayout card() {
        LinearLayout layout = vertical();
        layout.setBackground(rounded(cardColor(), border(), 8));
        return layout;
    }

    private LinearLayout compactCard() {
        LinearLayout layout = vertical();
        layout.setPadding(dp(12), dp(12), dp(12), dp(12));
        layout.setBackground(rounded(cardAlt(), border(), 8));
        return layout;
    }

    private EditText input(String hint, boolean password) {
        EditText input = new EditText(this);
        input.setTextColor(textColor());
        input.setHintTextColor(muted());
        input.setTextSize(15);
        input.setSingleLine(true);
        input.setHint(hint);
        input.setPadding(dp(12), 0, dp(12), 0);
        input.setBackground(rounded(inputBg(), border(), 8));
        input.setMinHeight(dp(48));
        input.setInputType(password
            ? InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_VARIATION_PASSWORD
            : InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_VARIATION_NORMAL);
        return input;
    }

    private Button primaryButton(String label) {
        Button button = new Button(this);
        button.setText(label);
        button.setTextColor(Color.WHITE);
        button.setTextSize(14);
        button.setTypeface(Typeface.DEFAULT, Typeface.BOLD);
        button.setAllCaps(false);
        button.setBackground(rounded(accent(), accent(), 8));
        button.setMinHeight(dp(48));
        return button;
    }

    private Button iconButton(String label) {
        Button button = new Button(this);
        button.setText(label);
        button.setTextColor(textColor());
        button.setTextSize(11);
        button.setAllCaps(false);
        button.setBackground(rounded(cardAlt(), border(), 8));
        button.setMinHeight(dp(38));
        button.setPadding(dp(9), 0, dp(9), 0);
        return button;
    }

    private ImageView logoView(int widthDp, int heightDp) {
        ImageView logo = new ImageView(this);
        logo.setImageResource(R.drawable.qa_logo);
        logo.setAdjustViewBounds(true);
        logo.setScaleType(ImageView.ScaleType.FIT_CENTER);
        logo.setMaxWidth(dp(widthDp));
        logo.setMaxHeight(dp(heightDp));
        return logo;
    }

    private TextView text(String value, int sp, int color, int style) {
        TextView view = new TextView(this);
        view.setText(value);
        view.setTextSize(sp);
        view.setTextColor(color);
        view.setTypeface(Typeface.DEFAULT, style);
        view.setLineSpacing(0, 1.08f);
        return view;
    }

    private LinearLayout vertical() {
        LinearLayout layout = new LinearLayout(this);
        layout.setOrientation(LinearLayout.VERTICAL);
        return layout;
    }

    private LinearLayout horizontal() {
        LinearLayout layout = new LinearLayout(this);
        layout.setOrientation(LinearLayout.HORIZONTAL);
        return layout;
    }

    private GradientDrawable rounded(int fill, int stroke, int radiusDp) {
        GradientDrawable drawable = new GradientDrawable();
        drawable.setColor(fill);
        drawable.setCornerRadius(dp(radiusDp));
        drawable.setStroke(dp(1), stroke);
        return drawable;
    }

    private LinearLayout.LayoutParams matchWrap() {
        return new LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, LinearLayout.LayoutParams.WRAP_CONTENT);
    }

    private LinearLayout.LayoutParams wrapWrap() {
        return new LinearLayout.LayoutParams(LinearLayout.LayoutParams.WRAP_CONTENT, LinearLayout.LayoutParams.WRAP_CONTENT);
    }

    private LinearLayout.LayoutParams sectionParams() {
        LinearLayout.LayoutParams params = matchWrap();
        params.setMargins(0, dp(12), 0, 0);
        return params;
    }

    private LinearLayout.LayoutParams spaced() {
        LinearLayout.LayoutParams params = matchWrap();
        params.setMargins(0, dp(10), 0, 0);
        return params;
    }

    private LinearLayout.LayoutParams spacedSmall() {
        LinearLayout.LayoutParams params = matchWrap();
        params.setMargins(0, dp(5), 0, 0);
        return params;
    }

    private LinearLayout.LayoutParams tabParams() {
        LinearLayout.LayoutParams params = wrapWrap();
        params.setMargins(0, 0, dp(8), 0);
        return params;
    }

    private LinearLayout.LayoutParams weightParams(float weight, int left, int right, int top, int bottom) {
        LinearLayout.LayoutParams params = new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, weight);
        params.setMargins(left, top, right, bottom);
        return params;
    }

    private JSONObject object(JSONObject parent, String key) {
        JSONObject object = parent == null ? null : parent.optJSONObject(key);
        return object == null ? new JSONObject() : object;
    }

    private String percentText(Object value) {
        if (value == null || JSONObject.NULL.equals(value)) {
            return "0%";
        }
        try {
            double number = value instanceof Number ? ((Number) value).doubleValue() : Double.parseDouble(String.valueOf(value));
            return percentText(number);
        } catch (Exception ignored) {
            return nonEmpty(String.valueOf(value), "0%");
        }
    }

    private String percentText(double value) {
        return String.format(Locale.US, "%.1f%%", value);
    }

    private String shortLabel(String value) {
        if (value == null) {
            return "Dia";
        }
        return value.length() > 10 ? value.substring(value.length() - 5) : value;
    }

    private int scoreColor(double score) {
        if (score < 0) {
            return muted();
        }
        if (score < 70) {
            return ROSE;
        }
        if (score < 85) {
            return AMBER;
        }
        return GREEN;
    }

    private int severityColor(String severity) {
        if ("critical".equalsIgnoreCase(severity)) {
            return ROSE;
        }
        if ("warning".equalsIgnoreCase(severity)) {
            return AMBER;
        }
        return CYAN;
    }

    private int bg() {
        return darkMode ? Color.rgb(10, 10, 10) : Color.rgb(247, 248, 250);
    }

    private int cardColor() {
        return darkMode ? Color.rgb(18, 18, 18) : Color.WHITE;
    }

    private int cardAlt() {
        return darkMode ? Color.rgb(25, 25, 25) : Color.rgb(250, 250, 251);
    }

    private int inputBg() {
        return darkMode ? Color.rgb(25, 25, 25) : Color.WHITE;
    }

    private int trackColor() {
        return darkMode ? Color.rgb(38, 38, 38) : Color.rgb(229, 231, 235);
    }

    private int border() {
        return darkMode ? Color.rgb(45, 45, 45) : Color.rgb(225, 228, 234);
    }

    private int textColor() {
        return darkMode ? Color.WHITE : Color.rgb(20, 20, 24);
    }

    private int muted() {
        return darkMode ? Color.rgb(166, 166, 176) : Color.rgb(91, 97, 110);
    }

    private int accent() {
        return darkMode ? GREEN : BLUE;
    }

    private int alpha(int color, int alpha) {
        return Color.argb(alpha, Color.red(color), Color.green(color), Color.blue(color));
    }

    private void applySystemBars() {
        getWindow().setStatusBarColor(bg());
        getWindow().setNavigationBarColor(bg());
    }

    private int dp(int value) {
        return (int) (value * getResources().getDisplayMetrics().density + 0.5f);
    }

    private void openUrl(String url) {
        if (url == null || url.trim().isEmpty()) {
            return;
        }
        startActivity(new Intent(Intent.ACTION_VIEW, Uri.parse(url)));
    }

    private void clearSession() {
        token = null;
        dashboardData = null;
        prefs.edit().remove("token").apply();
    }

    private String normalizeServer(String value) {
        String server = value == null ? "" : value.trim();
        if (server.endsWith("/")) {
            server = server.substring(0, server.length() - 1);
        }
        return server;
    }

    private String cleanError(Exception ex) {
        String message = ex.getMessage();
        return message == null || message.trim().isEmpty() ? "No se pudo conectar con QA365." : message;
    }

    private String nonEmpty(String value, String fallback) {
        return value == null || value.trim().isEmpty() || "null".equalsIgnoreCase(value) ? fallback : value;
    }

    private void renderError(String message) {
        content.removeAllViews();
        LinearLayout error = card();
        error.setPadding(dp(16), dp(16), dp(16), dp(16));
        error.addView(text("No se pudo cargar", 18, textColor(), Typeface.BOLD), matchWrap());
        error.addView(text(message, 14, muted(), Typeface.NORMAL), spacedSmall());
        Button retry = primaryButton("Reintentar");
        retry.setOnClickListener(v -> loadDashboard());
        error.addView(retry, spaced());
        content.addView(error, matchWrap());
    }

    private void runOnMain(Runnable runnable) {
        mainHandler.post(runnable);
    }

    private static class ApiException extends Exception {
        final int code;

        ApiException(int code, String message) {
            super(message);
            this.code = code;
        }
    }
}
