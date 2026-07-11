<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('page-title', 'TitanCore')</title>
  {{-- Fallback layout (module-contained). Host app layout should be preferred when available. --}}
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body>
  <div class="container py-4">
    <div class="mb-3">
      <h3 class="mb-0">@yield('page-title', 'TitanCore')</h3>
      <div class="text-muted">@yield('page-breadcrumb')</div>
    </div>

    @yield('content')
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
