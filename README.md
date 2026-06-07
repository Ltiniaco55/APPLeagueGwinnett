# Gwinnett Soccer League Hub (GYSL)

Plataforma web para gestión integral de competiciones deportivas infantiles, juveniles y amateur.

## Tecnologías utilizadas
- PHP
- MySQL / MariaDB
- Apache (XAMPP)
- HTML5
- CSS3
- JavaScript
- Git / GitHub

---

## Instalación rápida

### 1. Clonar repositorio
```bash
git clone https://github.com/Ltiniaco55/APPLeagueGwinnett.git
```

O descargar ZIP desde GitHub.

---

### 2. Copiar proyecto en XAMPP
Mover la carpeta del proyecto a:

```bash
C:\xampp\htdocs\
```

Debe quedar así:

```bash
C:\xampp\htdocs\Proyecto_intermodular
```

---

### 3. Crear base de datos
Abrir phpMyAdmin:

```bash
http://localhost/phpmyadmin
```

Crear base de datos:

```sql
gwinnett_league
```

---

### 4. Importar SQL
Importar archivo:

```bash
migrations/gwinnett_league_demo.sql
migrations/inserts.sql
```

---

### 5. Configurar conexión
Editar:

```bash
app/core/database.php
```

---

### 6. Ejecutar proyecto
Abrir:

```bash
http://localhost/Proyecto_intermodular
```

---

## Credenciales demo

### Admin
Email:
```bash
admin@gysl.local
```

Password:
```bash
Aa123456789
```

### Staff
Email:
```bash
staff1@gysl.local
```

Password:
```bash
Aa123456789
```

### Usuario
Email:
```bash
usuario@gysl.local
```

Password:
```bash
Aa123456789
```

---

## Funcionalidades principales
- Gestión de ligas
- Gestión de equipos
- Gestión de jugadores
- Gestión de plantillas
- Solicitudes alta/baja
- Roles y permisos
- Gestión de partidos
- Clasificaciones automáticas
- Vista pública
- Equipos favoritos
