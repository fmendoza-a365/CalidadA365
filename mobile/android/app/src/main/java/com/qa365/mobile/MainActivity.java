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
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.ScrollView;
import android.widget.TextView;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.OutputStream;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

public class MainActivity extends Activity {
    private static final String PREFS = "qa365_mobile";
    private static final String DEFAULT_SERVER = "https://qa365.com.pe";

    private static final int COLOR_BG = Color.rgb(10, 10, 10);
    private static final int COLOR_CARD = Color.rgb(18, 18, 18);
    private static final int COLOR_CARD_ALT = Color.rgb(24, 24, 24);
    private static final int COLOR_BORDER = Color.rgb(45, 45, 45);
    private static final int COLOR_TEXT = Color.WHITE;
    private static final int COLOR_MUTED = Color.rgb(170, 170, 178);
    private static final int COLOR_PRIMARY = Color.rgb(16, 185, 129);
    private static final int COLOR_WARNING = Color.rgb(251, 191, 36);
    private static final int COLOR_CRITICAL = Color.rgb(251, 113, 133);
    private static final int COLOR_INFO = Color.rgb(96, 165, 250);

    private final Handler mainHandler = new Handler(Looper.getMainLooper());
    private final ExecutorService executor = Executors.newSingleThreadExecutor();
    private SharedPreferences prefs;
    private LinearLayout root;
    private String token;
    private String serverUrl;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        prefs = getSharedPreferences(PREFS, MODE_PRIVATE);
        token = prefs.getString("token", null);
        serverUrl = prefs.getString("server_url", DEFAULT_SERVER);

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
        root = vertical();
        root.setPadding(dp(22), dp(34), dp(22), dp(22));
        root.setGravity(Gravity.CENTER_HORIZONTAL);
        root.setBackgroundColor(COLOR_BG);

        TextView logo = text("QA365", 34, COLOR_TEXT, Typeface.BOLD);
        logo.setGravity(Gravity.CENTER);
        root.addView(logo, matchWrap());

        TextView subtitle = text("Resultados y alertas de calidad", 15, COLOR_MUTED, Typeface.NORMAL);
        subtitle.setGravity(Gravity.CENTER);
        root.addView(subtitle, matchWrap());

        LinearLayout card = card();
        card.setPadding(dp(18), dp(18), dp(18), dp(18));
        LinearLayout.LayoutParams cardParams = matchWrap();
        cardParams.setMargins(0, dp(28), 0, 0);

        EditText serverInput = input("Servidor", false);
        serverInput.setText(serverUrl);
        card.addView(serverInput, matchWrap());

        EditText loginInput = input("Usuario o email", false);
        LinearLayout.LayoutParams inputParams = matchWrap();
        inputParams.setMargins(0, dp(12), 0, 0);
        card.addView(loginInput, inputParams);

        EditText passwordInput = input("Contrasena", true);
        passwordInput.setImeOptions(EditorInfo.IME_ACTION_DONE);
        LinearLayout.LayoutParams passwordParams = matchWrap();
        passwordParams.setMargins(0, dp(12), 0, 0);
        card.addView(passwordInput, passwordParams);

        Button loginButton = primaryButton("Ingresar");
        LinearLayout.LayoutParams buttonParams = matchWrap();
        buttonParams.setMargins(0, dp(16), 0, 0);
        card.addView(loginButton, buttonParams);

        TextView status = text("", 13, COLOR_MUTED, Typeface.NORMAL);
        LinearLayout.LayoutParams statusParams = matchWrap();
        statusParams.setMargins(0, dp(12), 0, 0);
        card.addView(status, statusParams);

        root.addView(card, cardParams);
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
        root = vertical();
        root.setBackgroundColor(COLOR_BG);
        root.setPadding(dp(16), dp(22), dp(16), dp(16));

        LinearLayout header = horizontal();
        header.setGravity(Gravity.CENTER_VERTICAL);
        TextView title = text("QA365 Mobile", 22, COLOR_TEXT, Typeface.BOLD);
        header.addView(title, new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1));

        Button refreshButton = secondaryButton("Actualizar");
        header.addView(refreshButton, wrapWrap());

        Button logoutButton = secondaryButton("Salir");
        LinearLayout.LayoutParams logoutParams = wrapWrap();
        logoutParams.setMargins(dp(8), 0, 0, 0);
        header.addView(logoutButton, logoutParams);
        root.addView(header, matchWrap());

        ScrollView scrollView = new ScrollView(this);
        LinearLayout content = vertical();
        content.setPadding(0, dp(16), 0, dp(20));
        scrollView.addView(content);
        root.addView(scrollView, new LinearLayout.LayoutParams(
            LinearLayout.LayoutParams.MATCH_PARENT,
            0,
            1
        ));

        setContentView(root);

        refreshButton.setOnClickListener(v -> loadDashboard(content));
        logoutButton.setOnClickListener(v -> logout());
        loadDashboard(content);
    }

    private void loadDashboard(LinearLayout content) {
        content.removeAllViews();
        LinearLayout loading = card();
        loading.setGravity(Gravity.CENTER);
        loading.setPadding(dp(18), dp(24), dp(18), dp(24));
        loading.addView(new ProgressBar(this), wrapWrap());
        TextView loadingText = text("Cargando datos...", 14, COLOR_MUTED, Typeface.NORMAL);
        LinearLayout.LayoutParams textParams = wrapWrap();
        textParams.setMargins(dp(12), 0, 0, 0);
        loading.addView(loadingText, textParams);
        content.addView(loading, matchWrap());

        executor.execute(() -> {
            try {
                JSONObject summary = request("GET", "/api/mobile/summary", null);
                JSONObject alerts = request("GET", "/api/mobile/alerts?limit=12", null);
                JSONObject evaluations = request("GET", "/api/mobile/evaluations?per_page=12", null);

                runOnMain(() -> renderDashboard(content, summary, alerts, evaluations));
            } catch (ApiException ex) {
                if (ex.code == 401) {
                    clearSession();
                    runOnMain(this::showLogin);
                    return;
                }
                runOnMain(() -> renderError(content, ex.getMessage()));
            } catch (Exception ex) {
                runOnMain(() -> renderError(content, cleanError(ex)));
            }
        });
    }

    private void renderDashboard(LinearLayout content, JSONObject summaryResponse, JSONObject alertsResponse, JSONObject evaluationsResponse) {
        content.removeAllViews();
        JSONObject summary = summaryResponse.optJSONObject("summary");
        if (summary == null) {
            summary = new JSONObject();
        }

        LinearLayout summaryGrid = vertical();
        summaryGrid.addView(summaryRow(
            metricCard("Promedio", summary.optString("average_score", "0") + "%", COLOR_PRIMARY),
            metricCard("Alertas", summary.optString("open_alerts", "0"), COLOR_WARNING)
        ));
        summaryGrid.addView(summaryRow(
            metricCard("Pendientes", summary.optString("pending_review", "0"), COLOR_INFO),
            metricCard("Criticas", summary.optString("critical_scores", "0"), COLOR_CRITICAL)
        ));
        content.addView(summaryGrid, matchWrap());

        JSONArray alerts = alertsResponse.optJSONArray("alerts");
        renderAlerts(content, alerts == null ? new JSONArray() : alerts);

        JSONArray evaluations = evaluationsResponse.optJSONArray("data");
        renderEvaluations(content, evaluations == null ? new JSONArray() : evaluations);
    }

    private void renderAlerts(LinearLayout content, JSONArray alerts) {
        LinearLayout section = section("Alertas");

        if (alerts.length() == 0) {
            section.addView(text("No hay alertas abiertas.", 14, COLOR_MUTED, Typeface.NORMAL), matchWrap());
        }

        for (int i = 0; i < alerts.length(); i++) {
            JSONObject alert = alerts.optJSONObject(i);
            if (alert == null) {
                continue;
            }
            LinearLayout item = compactCard();
            item.setOnClickListener(v -> openUrl(alert.optString("action_url", "")));

            String severity = alert.optString("severity", "info");
            int color = severityColor(severity);
            item.addView(text(alert.optString("title", "Alerta"), 15, COLOR_TEXT, Typeface.BOLD), matchWrap());
            TextView description = text(alert.optString("description", ""), 13, COLOR_MUTED, Typeface.NORMAL);
            LinearLayout.LayoutParams descParams = matchWrap();
            descParams.setMargins(0, dp(5), 0, 0);
            item.addView(description, descParams);
            TextView chip = chip(severity.toUpperCase(), color);
            LinearLayout.LayoutParams chipParams = wrapWrap();
            chipParams.setMargins(0, dp(8), 0, 0);
            item.addView(chip, chipParams);

            LinearLayout.LayoutParams itemParams = matchWrap();
            itemParams.setMargins(0, dp(10), 0, 0);
            section.addView(item, itemParams);
        }

        content.addView(section, sectionParams());
    }

    private void renderEvaluations(LinearLayout content, JSONArray evaluations) {
        LinearLayout section = section("Ultimas evaluaciones");

        if (evaluations.length() == 0) {
            section.addView(text("Aun no hay evaluaciones visibles para tu usuario.", 14, COLOR_MUTED, Typeface.NORMAL), matchWrap());
        }

        for (int i = 0; i < evaluations.length(); i++) {
            JSONObject evaluation = evaluations.optJSONObject(i);
            if (evaluation == null) {
                continue;
            }

            LinearLayout item = compactCard();
            item.setOnClickListener(v -> openUrl(evaluation.optString("action_url", "")));

            LinearLayout top = horizontal();
            top.setGravity(Gravity.CENTER_VERTICAL);
            TextView main = text(nonEmpty(evaluation.optString("campaign"), "Sin campana"), 15, COLOR_TEXT, Typeface.BOLD);
            top.addView(main, new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1));
            top.addView(scoreChip(evaluation.optString("score_label", "Sin nota"), evaluation.optDouble("score", -1)));
            item.addView(top, matchWrap());

            String agent = nonEmpty(evaluation.optString("agent"), "Sin asesor");
            String status = nonEmpty(evaluation.optString("status_label"), "Sin estado");
            TextView meta = text(agent + " - " + status, 13, COLOR_MUTED, Typeface.NORMAL);
            LinearLayout.LayoutParams metaParams = matchWrap();
            metaParams.setMargins(0, dp(5), 0, 0);
            item.addView(meta, metaParams);

            JSONObject indicators = evaluation.optJSONObject("feedback_indicators");
            String risk = indicators == null ? "" : indicators.optString("customer_experience_risk", "");
            String unresolved = indicators == null ? "" : String.valueOf(indicators.opt("customer_left_unresolved"));
            JSONObject audio = evaluation.optJSONObject("audio");
            String deadAir = audio == null ? "" : audio.optString("dead_air_label", "");
            String detail = "Riesgo: " + nonEmpty(risk, "No detectado")
                + " | Sin resolver: " + readableBoolean(unresolved)
                + " | Tiempo muerto: " + nonEmpty(deadAir, "00:00");

            TextView details = text(detail, 12, COLOR_MUTED, Typeface.NORMAL);
            LinearLayout.LayoutParams detailsParams = matchWrap();
            detailsParams.setMargins(0, dp(6), 0, 0);
            item.addView(details, detailsParams);

            LinearLayout.LayoutParams itemParams = matchWrap();
            itemParams.setMargins(0, dp(10), 0, 0);
            section.addView(item, itemParams);
        }

        content.addView(section, sectionParams());
    }

    private void renderError(LinearLayout content, String message) {
        content.removeAllViews();
        LinearLayout error = card();
        error.setPadding(dp(18), dp(18), dp(18), dp(18));
        error.addView(text("No se pudo cargar", 18, COLOR_TEXT, Typeface.BOLD), matchWrap());
        TextView detail = text(message, 14, COLOR_MUTED, Typeface.NORMAL);
        LinearLayout.LayoutParams detailParams = matchWrap();
        detailParams.setMargins(0, dp(8), 0, 0);
        error.addView(detail, detailParams);
        content.addView(error, matchWrap());
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

    private LinearLayout section(String titleText) {
        LinearLayout section = card();
        section.setPadding(dp(14), dp(14), dp(14), dp(14));
        section.addView(text(titleText, 17, COLOR_TEXT, Typeface.BOLD), matchWrap());
        return section;
    }

    private LinearLayout summaryRow(View left, View right) {
        LinearLayout row = horizontal();
        LinearLayout.LayoutParams leftParams = new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1);
        leftParams.setMargins(0, dp(8), dp(6), 0);
        LinearLayout.LayoutParams rightParams = new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1);
        rightParams.setMargins(dp(6), dp(8), 0, 0);
        row.addView(left, leftParams);
        row.addView(right, rightParams);
        return row;
    }

    private LinearLayout metricCard(String label, String value, int color) {
        LinearLayout box = compactCard();
        box.addView(text(label, 12, COLOR_MUTED, Typeface.NORMAL), matchWrap());
        TextView metric = text(value, 25, color, Typeface.BOLD);
        LinearLayout.LayoutParams metricParams = matchWrap();
        metricParams.setMargins(0, dp(4), 0, 0);
        box.addView(metric, metricParams);
        return box;
    }

    private LinearLayout card() {
        LinearLayout layout = vertical();
        layout.setBackground(rounded(COLOR_CARD, COLOR_BORDER, 8));
        return layout;
    }

    private LinearLayout compactCard() {
        LinearLayout layout = vertical();
        layout.setPadding(dp(12), dp(12), dp(12), dp(12));
        layout.setBackground(rounded(COLOR_CARD_ALT, COLOR_BORDER, 8));
        return layout;
    }

    private EditText input(String hint, boolean password) {
        EditText input = new EditText(this);
        input.setTextColor(COLOR_TEXT);
        input.setHintTextColor(COLOR_MUTED);
        input.setTextSize(15);
        input.setSingleLine(true);
        input.setHint(hint);
        input.setPadding(dp(12), 0, dp(12), 0);
        input.setBackground(rounded(Color.rgb(25, 25, 25), COLOR_BORDER, 8));
        input.setMinHeight(dp(48));
        input.setInputType(password
            ? InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_VARIATION_PASSWORD
            : InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_VARIATION_NORMAL);
        return input;
    }

    private Button primaryButton(String text) {
        Button button = new Button(this);
        button.setText(text);
        button.setTextColor(Color.rgb(8, 8, 8));
        button.setTextSize(14);
        button.setTypeface(Typeface.DEFAULT, Typeface.BOLD);
        button.setAllCaps(false);
        button.setBackground(rounded(Color.WHITE, Color.WHITE, 8));
        button.setMinHeight(dp(48));
        return button;
    }

    private Button secondaryButton(String text) {
        Button button = new Button(this);
        button.setText(text);
        button.setTextColor(COLOR_TEXT);
        button.setTextSize(12);
        button.setAllCaps(false);
        button.setBackground(rounded(COLOR_CARD_ALT, COLOR_BORDER, 8));
        button.setMinHeight(dp(40));
        button.setPadding(dp(10), 0, dp(10), 0);
        return button;
    }

    private TextView scoreChip(String text, double score) {
        int color = score < 0 ? COLOR_MUTED : score < 70 ? COLOR_CRITICAL : score < 85 ? COLOR_WARNING : COLOR_PRIMARY;
        return chip(text, color);
    }

    private TextView chip(String label, int color) {
        TextView chip = text(label, 12, color, Typeface.BOLD);
        chip.setPadding(dp(8), dp(4), dp(8), dp(4));
        chip.setBackground(rounded(adjustAlpha(color, 38), adjustAlpha(color, 120), 20));
        return chip;
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

    private int adjustAlpha(int color, int alpha) {
        return Color.argb(alpha, Color.red(color), Color.green(color), Color.blue(color));
    }

    private int severityColor(String severity) {
        if ("critical".equalsIgnoreCase(severity)) {
            return COLOR_CRITICAL;
        }
        if ("warning".equalsIgnoreCase(severity)) {
            return COLOR_WARNING;
        }
        return COLOR_INFO;
    }

    private LinearLayout.LayoutParams matchWrap() {
        return new LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, LinearLayout.LayoutParams.WRAP_CONTENT);
    }

    private LinearLayout.LayoutParams wrapWrap() {
        return new LinearLayout.LayoutParams(LinearLayout.LayoutParams.WRAP_CONTENT, LinearLayout.LayoutParams.WRAP_CONTENT);
    }

    private LinearLayout.LayoutParams sectionParams() {
        LinearLayout.LayoutParams params = matchWrap();
        params.setMargins(0, dp(14), 0, 0);
        return params;
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
        if (message == null || message.trim().isEmpty()) {
            return "No se pudo conectar con QA365.";
        }
        return message;
    }

    private String nonEmpty(String value, String fallback) {
        return value == null || value.trim().isEmpty() || "null".equalsIgnoreCase(value) ? fallback : value;
    }

    private String readableBoolean(String value) {
        if ("true".equalsIgnoreCase(value) || "1".equals(value)) {
            return "Si";
        }
        if ("false".equalsIgnoreCase(value) || "0".equals(value)) {
            return "No";
        }
        return "No detectado";
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
