# QA365 Mobile

App Android nativa para consultar resumen, alertas y ultimas evaluaciones de QA365.

## Abrir en Android Studio

1. Abrir Android Studio.
2. Seleccionar `Open`.
3. Elegir la carpeta `mobile/android`.
4. Ejecutar `app` o compilar `Build > Build Bundle(s) / APK(s) > Build APK(s)`.

## Compilar por consola

```bash
JAVA_HOME=/home/fmendoza/android-studio/jbr PATH=/home/fmendoza/android-studio/jbr/bin:$PATH ./gradlew --offline :app:assembleDebug
```

El APK generado queda en:

```text
app/build/outputs/apk/debug/app-debug.apk
```

La app usa por defecto `https://qa365.com.pe`, pero el campo Servidor permite probar otra URL como `http://10.0.2.2:8000` desde un emulador local.
