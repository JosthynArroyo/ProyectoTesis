# Proyecto Clínica

Este repositorio contiene una aplicación **Laravel** para la gestión integral de una clínica médica.  
El sistema permite administrar pacientes, doctores, citas y procesos clínicos de forma sencilla y organizada.

## Funcionalidades principales

- **Gestión de usuarios y roles**
  - Administrador: crea y administra doctores y pacientes.
  - Doctor: revisa, acepta o cancela citas y gestiona su perfil.
  - Paciente: se registra, agenda, reprograma o cancela citas médicas.

- **Gestión de citas médicas**
  - Registro, modificación y cancelación de citas.
  - Validación para evitar duplicidad de citas en el mismo horario con el mismo doctor.
  - Historial de citas por paciente.

- **Control clínico**
  - Registro de especialidades médicas.
  - Asociación de doctores a sus especialidades.
  - Información clínica básica por paciente.

- **Seguridad**
  - Autenticación y autorización basada en roles.
  - Validación de datos únicos como número de cédula y correo electrónico.
  - Paneles separados para cada tipo de usuario.

## Arquitectura

El sistema está desarrollado en **Laravel** utilizando el patrón **MVC** (Modelo – Vista – Controlador).  
La base de datos se diseñó en **MySQL**, y las vistas utilizan **Blade** junto con **TailwindCSS** para un diseño moderno y responsivo.  
Se implementó control de roles a nivel de controladores y middleware para garantizar la seguridad y organización del flujo de trabajo.

## Despliegue

El sistema está preparado para ejecutarse en entornos con **PHP-FPM** y servidores web como **Nginx** o **Apache**.  
También puede integrarse con servicios en la nube para mayor escalabilidad y disponibilidad.


