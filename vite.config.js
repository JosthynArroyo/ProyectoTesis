import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'

export default defineConfig({
  base: '',
  build: {
    outDir: 'public/build',
    assetsDir: 'assets',
    manifest: 'manifest.json',
    emptyOutDir: true,
  },
  plugins: [
    laravel({
      input: [
        // CSS
        'resources/css/app.css',
        'resources/css/login.css',
        'resources/css/register.css',
        'resources/css/welcome.css',
        'resources/css/servicios.css',
        'resources/css/navbar.css',
        'resources/css/modal.css',
        'resources/css/contacto.css',
        'resources/css/panel/account-pages.css',
        'resources/css/dashboards/admin.css',
        'resources/css/dashboards/citas.css',
        'resources/css/dashboards/doctor.css',
        'resources/css/paciente/citas.css',
        //Paciente
        'resources/css/paciente/crear-cita.css',
        'resources/css/paciente/editar-cita.css',
        'resources/css/paciente/paciente.css',
        'resources/css/paciente/perfil.css',
        'resources/css/paciente/perfil.css',
        //Admin
        'resources/css/admin/usuarios.css',
        'resources/css/admin/users/edit.css',
        'resources/css/admin/create-user.css',
        'resources/css/admin/cambios-citas.css',
        'resources/css/admin/perfil.css',
        'resources/css/admin/users/form.css',
        'resources/css/admin/horarios/create.css',
        'resources/css/admin/horarios/edit.css',
        'resources/css/admin/horarios/index.css',
        'resources/css/admin/users/show.css',
        //Doctor
        'resources/css/doctor/agenda.css',
        'resources/css/doctor/horario.css',
        'resources/css/doctor/perfil.css',
        

        // JS
        'resources/js/app.js',
        'resources/js/bootstrap.js',
        'resources/js/sidebar-toggle.js',
        'resources/js/navbar.js',
        'resources/js/servicios.js',
        'resources/js/welcome-login-modal.js',
        'resources/js/welcome-carousel.js',
        'resources/js/dashboard-admin.js',
        'resources/js/dashboard-admin-extras.js',
        'resources/js/dashboard-doctor.js',
        //Doctor
        'resources/js/doctor/agenda.js',
        'resources/js/doctor/perfil.js',
        //Paciente
        'resources/js/paciente/citas.js',
        'resources/js/paciente/crear-cita.js',
        'resources/js/paciente/dashboard-paciente.js',
        'resources/js/paciente/perfil.js',
        //Admin
        'resources/js/admin/usuarios.js',
        'resources/js/admin/users/edit.js',
        'resources/js/admin/create-user.js',
        'resources/js/admin/perfil.js',
        'resources/js/admin/users/form.js',
        'resources/js/admin/horarios/create.js',
        'resources/js/admin/horarios/edit.js',
        'resources/js/admin/horarios/index.js',
        'resources/js/admin/users/show.js',
      ],
      refresh: true,
    }),
  ],
})