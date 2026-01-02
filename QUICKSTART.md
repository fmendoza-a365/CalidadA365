# 🚀 Guía Rápida de Inicio

## Inicio Rápido (5 minutos)

### 1. Instalar y Configurar
```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

### 2. Configurar Base de Datos
Editar `.env`:
```env
DB_DATABASE=calidad_qa
DB_USERNAME=root
DB_PASSWORD=tu_password
```

### 3. Migrar y Poblar
```bash
php artisan migrate
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=UserSeeder
php artisan db:seed --class=DemoDataSeeder
```

### 4. Compilar y Servir
```bash
npm run build
php artisan serve
```

### 5. Acceder
- URL: http://localhost:8000
- Login: admin@qa.com / password

## Flujo de Trabajo Típico

### Como QA Manager

1. **Crear Campaña**
   - Campañas → Nueva Campaña
   - Nombre: "Atención Telefónica"
   - Guardar

2. **Asignar Asesor**
   - En campaña → Asignaciones
   - Asesor: agent@qa.com
   - Supervisor: supervisor@qa.com
   - Guardar

3. **Cargar Transcripción**
   - Transcripciones → Cargar
   - Seleccionar campaña y asesor
   - Subir archivo .txt
   - El sistema evalúa automáticamente

### Como Asesor

1. **Ver Evaluaciones**
   - Login: agent@qa.com / password
   - Dashboard muestra evaluaciones pendientes

2. **Revisar Detalles**
   - Click en "Ver Detalles"
   - Revisar puntaje y evidencias

3. **Responder**
   - Aceptar: Agregar compromiso de mejora
   - Refutar: Explicar motivo de desacuerdo

### Como Supervisor

1. **Monitorear Equipo**
   - Login: supervisor@qa.com / password
   - Dashboard muestra desempeño del equipo

2. **Ver Evaluaciones**
   - Evaluaciones → Filtrar por campaña
   - Revisar detalles de cada evaluación

## Comandos Útiles

```bash
# Limpiar caché
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# Procesar cola de jobs
php artisan queue:work

# Recompilar assets
npm run dev

# Ver rutas
php artisan route:list

# Crear usuario manualmente
php artisan tinker
>>> $user = User::create(['name' => 'Test', 'email' => 'test@test.com', 'password' => Hash::make('password')]);
>>> $user->assignRole('agent');
```

## Archivo de Transcripción de Ejemplo

Crear archivo `ejemplo.txt`:
```
Asesor: Buenos días, mi nombre es Juan de Telecom, ¿con quién tengo el gusto?
Cliente: Hola, soy María González.
Asesor: Mucho gusto María. ¿Me confirma su número de documento?
Cliente: Sí, es 1234567890.
Asesor: Perfecto. ¿En qué puedo ayudarle?
Cliente: Tengo un problema con mi factura.
Asesor: Entiendo su preocupación. Déjeme revisar.
...
```

## Solución de Problemas Comunes

### Error: "SQLSTATE[HY000] [1049] Unknown database"
```bash
# Crear base de datos
mysql -u root -p
CREATE DATABASE calidad_qa;
exit;
php artisan migrate
```

### Error: "Class 'Redis' not found"
```env
# Cambiar en .env
QUEUE_CONNECTION=sync
```

### Assets no se cargan
```bash
npm run build
php artisan config:clear
```

### Jobs no se procesan
```bash
# Terminal 1
php artisan serve

# Terminal 2
php artisan queue:work
```

## Datos de Demostración

El seeder `DemoDataSeeder` crea:
- 1 Campaña: "Atención al Cliente - Telefonía"
- 1 Ficha de Calidad con 3 atributos:
  - Protocolo de Atención (30%)
  - Empatía y Comunicación (30%)
  - Resolución del Caso (40%)
- 10 Subatributos con pesos balanceados
- 1 Asignación: agent → supervisor

## Próximos Pasos

1. Explorar los dashboards de cada rol
2. Cargar transcripciones de prueba
3. Revisar el flujo completo de evaluación
4. Personalizar fichas de calidad
5. Configurar IA real (OpenAI/Claude)

---

¿Necesitas ayuda? Revisa el [README.md](README.md) completo.
