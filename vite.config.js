import { defineConfig } from 'vite'
import tailwindcss from '@tailwindcss/vite'
import laravel from 'laravel-vite-plugin'
import { fileURLToPath } from 'node:url'

export default defineConfig({
  base: '',
  resolve: {
    alias: {
      '@tensorflow/tfjs-core': fileURLToPath(new URL('./node_modules/@tensorflow/tfjs-core/dist/index.js', import.meta.url)),
    },
  },
  build: {
    outDir: 'public/build',
    assetsDir: 'assets',
    manifest: 'manifest.json',
    emptyOutDir: true,
    rollupOptions: {
      output: {
        manualChunks(id) {
          if (!id.includes('node_modules')) return undefined

          if (id.includes('face-api.js')) {
            return 'face-api'
          }

          if (id.includes('@tensorflow/tfjs-core')) {
            if (id.includes('/backends/webgl/') || id.includes('\\backends\\webgl\\')) {
              return 'tfjs-webgl'
            }

            if (id.includes('/backends/cpu/') || id.includes('\\backends\\cpu\\')) {
              return 'tfjs-cpu'
            }

            if (id.includes('/ops/') || id.includes('\\ops\\')) {
              return 'tfjs-ops'
            }

            return 'tfjs-core'
          }

          return 'vendor'
        },
      },
    },
  },
  plugins: [
    tailwindcss(),
    laravel({
      input: [
        // CSS
        'resources/css/app.css',
        'resources/css/panel-theme.css',
        'resources/css/login.css',
        'resources/css/register.css',
        'resources/css/welcome.css',
        'resources/css/servicios.css',
        'resources/css/navbar.css',
        'resources/css/modal.css',
        'resources/css/contacto.css',
        'resources/css/chatbot/widget.css',
        'resources/css/panel/account-pages.css',
        'resources/css/panel/weekly-schedule.css',
        'resources/css/auth/password-email.css',
        'resources/css/auth/password-reset.css',
        'resources/css/dashboards/admin.css',
        'resources/css/dashboards/citas.css',
        'resources/css/dashboards/doctor.css',
        'resources/css/dashboards/laboratorio.css',
        'resources/css/paciente/citas.css',
        //Paciente
        'resources/css/paciente/crear-cita.css',
        'resources/css/paciente/editar-cita.css',
        'resources/css/paciente/paciente.css',
        'resources/css/paciente/dashboard.css',
        'resources/css/paciente/perfil.css',
        'resources/css/paciente/laboratorio.css',
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
        'resources/css/admin/dashboard-new.css',
        'resources/css/admin/cambios-citas-pdf.css',
        'resources/css/admin/usuarios-pdf.css',
        //Doctor
        'resources/css/doctor/agenda.css',
        'resources/css/doctor/horario.css',
        'resources/css/doctor/perfil.css',
        'resources/css/doctor/citas.css',
        'resources/css/doctor/pacientes-index.css',
        'resources/css/doctor/recetas-form.css',
        'resources/css/doctor/recetas-index.css',
        'resources/css/doctor/receta-pdf.css',
        'resources/css/doctor/laboratorio.css',
        // Emails / PDFs
        'resources/css/emails/cita-estado.css',


        // JS
        'resources/js/app.js',
        'resources/js/panel-theme.js',
        'resources/js/sidebar-toggle.js',
        'resources/js/navbar.js',
        'resources/js/chatbot/widget.js',
        'resources/js/servicios.js',
        'resources/js/face-login-modal.js',
        'resources/js/welcome-login-modal.js',
        'resources/js/welcome-carousel.js',
        'resources/js/dashboard-admin.js',
        'resources/js/dashboard-admin-extras.js',
        'resources/js/dashboard-doctor.js',
        'resources/js/contacto.js',
        //Doctor
        'resources/js/doctor/agenda.js',
        'resources/js/doctor/citas.js',
        'resources/js/doctor/laboratorio-create.js',
        'resources/js/doctor/perfil.js',
        'resources/js/doctor/recetas-editar.js',
        'resources/js/doctor/clinical-record-editor.js',
        'resources/js/doctor/soap.js',
        //Paciente
        'resources/js/paciente/crear-cita.js',
        'resources/js/paciente/editar-cita.js',
        'resources/js/paciente/dashboard-paciente.js',
        'resources/js/paciente/lab-order.js',
        'resources/js/paciente/perfil.js',
        //Laboratorio
        'resources/js/laboratorio/ordenes.js',
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
        'resources/js/admin/cambios-citas.js',
        'resources/js/admin/override-create.js',
        'resources/js/admin/personalizacion-modal.js',
        'resources/js/admin/personalizacion-bienvenida.js',
        'resources/js/admin/personalizacion-drafts.js',
        'resources/js/admin/personalizacion-servicios.js',
        // Auth
        'resources/js/auth/password-toggle.js',
        'resources/js/auth/password-reset.js',
        'resources/js/auth/face-enroll.js',
        'resources/js/auth/face-login.js',
      ],
      refresh: true,
    }),
  ],
})
