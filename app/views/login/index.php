<?php
$basePath = $basePath ?? ($baseUrl ?? '/');
$contact = $contact ?? [];
$supportName = $contact['support_name'] ?? 'Soporte';
$supportPhone = $contact['support_phone'] ?? '';
$supportEmail = $contact['support_email'] ?? '';
$company = $contact['company'] ?? 'Proveedor Nova Hub';
$year = $contact['year'] ?? date('Y');
$errorMessage = $error ?? null;
$statusMessage = $status ?? null;
$loginAction = ($basePath !== '' ? $basePath : '') . '/login';
?>

<!-- Loader -->
<div class="page-loader" role="status" aria-live="polite">
  <div class="loader-pulse"></div>
  <span class="loader-text">Cargando portal</span>
</div>

<!-- Escena principal -->
<div class="min-h-[100svh] w-full bg-background-dark font-display text-text-light flex flex-col lg:flex-row">
  <!-- Columna izquierda: Hero / Arte (oculta en móviles) -->
  <section class="relative hidden lg:flex lg:w-1/2 items-center justify-center overflow-hidden p-8 xl:p-12">
    <div class="absolute inset-0 z-0 pointer-events-none">
      <div class="super-flower flower-1"></div>
      <div class="super-flower flower-2"></div>

      <!-- Burbujas animadas -->
      <div class="bubble"></div>
      <div class="bubble"></div>
      <div class="bubble"></div>
      <div class="bubble"></div>
      <div class="bubble"></div>
      <div class="bubble"></div>
      <div class="bubble"></div>
    </div>

    <div class="relative z-10 text-center max-w-xl mx-auto">
      <div class="inline-block p-4 bg-primary/20 rounded-full mb-6 shadow-lg ring-1 ring-primary/30">
        <!-- Icono decorativo -->
        <svg class="w-16 h-16 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
        </svg>
      </div>

      <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight text-white mb-4">
        Bienvenido a Nuestro Portal de Proveedores
      </h1>
      <p class="text-base md:text-lg text-text-dark">
        Acceda a todas sus herramientas, gestione órdenes, revise inventario y mucho más. Todo en un solo lugar.
      </p>
    </div>
  </section>

  <!-- Columna derecha: Panel de login -->
  <main class="flex w-full lg:w-1/2 items-center justify-center px-4 sm:px-6 py-8">
    <div class="w-full max-w-md">
      <!-- Encabezado -->
      <div class="mb-6 sm:mb-8 flex items-center gap-3">
        <div class="size-8 text-primary" aria-hidden="true">
          <svg fill="currentColor" viewBox="0 0 24 24" class="w-8 h-8">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-12h2v4h-2zm0 6h2v2h-2z"></path>
          </svg>
        </div>
        <h2 class="text-white text-2xl font-bold tracking-[-0.015em]">Iniciar Sesión</h2>
      </div>

      <!-- Alerts -->
      <?php if (!empty($errorMessage)): ?>
        <div class="mb-4 rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2.5 text-sm text-red-300 flex items-start gap-2" role="alert">
          <span class="material-symbols-outlined text-red-300">error</span>
          <span><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      <?php endif; ?>

      <?php if (!empty($statusMessage)): ?>
        <div class="mb-4 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-3 py-2.5 text-sm text-emerald-300 flex items-start gap-2" role="status">
          <span class="material-symbols-outlined text-emerald-300">check_circle</span>
          <span><?= htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      <?php endif; ?>

      <!-- Card -->
      <div class="rounded-xl bg-black/30 backdrop-blur-xl p-6 sm:p-8 shadow-2xl ring-1 ring-primary/20">
        <form id="login-form" class="space-y-6" action="<?= htmlspecialchars($loginAction, ENT_QUOTES, 'UTF-8') ?>" method="POST" autocomplete="off" novalidate>
          <!-- Usuario -->
          <div class="flex flex-col">
            <label for="username" class="text-text-dark text-sm font-medium pb-2">Usuario</label>
            <input
              type="text"
              id="username"
              name="username"
              placeholder="Nombre de usuario"
              required
              autofocus
              class="form-input h-12 w-full rounded-lg border border-primary/20 bg-black/30 p-4 text-base text-text-light placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-primary/50"
            />
          </div>

          <!-- Password -->
          <div class="flex flex-col">
            <label for="password" class="text-text-dark text-sm font-medium pb-2">Contraseña</label>
            <div class="relative flex w-full items-center">
              <input
                type="password"
                id="password"
                name="password"
                placeholder="Contraseña"
                required
                class="form-input h-12 w-full rounded-lg border border-primary/20 bg-black/30 p-4 pr-12 text-base text-text-light placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-primary/50"
              />
              <button type="button" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-primary transition-colors password-toggle" aria-label="Mostrar contraseña" data-visible="false">
                <span class="material-symbols-outlined">visibility</span>
              </button>
            </div>
          </div>

          <!-- Submit -->
          <button type="submit" class="btn-submit relative flex w-full cursor-pointer items-center justify-center overflow-hidden rounded-lg h-12 px-5 bg-primary text-white text-base font-bold tracking-[0.015em] transition-all hover:bg-primary/80 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:ring-offset-2 focus:ring-offset-background-dark">
            <span class="btn-label truncate">Acceder</span>
            <span class="btn-icon ml-2 inline-grid size-9 place-items-center rounded-full bg-white/15">
              <i class="fas fa-arrow-right" aria-hidden="true"></i>
            </span>
            <span class="btn-ripple" aria-hidden="true"></span>
          </button>
        </form>
      </div>

      <!-- Soporte -->
      <div class="mt-6 sm:mt-8 text-center text-sm text-text-dark">
        <?php if ($supportEmail !== ''): ?>
          <p>¿Necesitas ayuda? <a class="font-medium text-primary hover:text-primary/80" href="mailto:<?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?>">Contacta con soporte</a></p>
        <?php else: ?>
          <p>¿Necesitas ayuda? <span class="opacity-75"><?= htmlspecialchars($supportName, ENT_QUOTES, 'UTF-8') ?></span></p>
        <?php endif; ?>
      </div>

      <!-- Footer -->
      <footer class="mt-8 text-center text-xs text-text-dark/80">
        <p class="mb-2">&copy; <?= htmlspecialchars($year, ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($company, ENT_QUOTES, 'UTF-8') ?></p>
        <div class="flex items-center justify-center gap-4 flex-wrap">
          <?php if ($supportPhone !== ''): ?>
            <a class="inline-flex items-center gap-1 hover:text-primary transition-colors" href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', $supportPhone), ENT_QUOTES, 'UTF-8') ?>">
              <i class="fas fa-phone text-xs"></i><span><?= htmlspecialchars($supportPhone, ENT_QUOTES, 'UTF-8') ?></span>
            </a>
          <?php endif; ?>
          <?php if ($supportEmail !== ''): ?>
            <a class="inline-flex items-center gap-1 hover:text-primary transition-colors" href="mailto:<?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?>">
              <i class="fas fa-envelope text-xs"></i><span><?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?></span>
            </a>
          <?php endif; ?>
        </div>
      </footer>
    </div>
  </main>
</div>
