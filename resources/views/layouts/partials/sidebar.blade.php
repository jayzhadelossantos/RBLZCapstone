@php
    $user = auth()->user();
    $role = $user?->role;
    $portalLabel = match ($role) {
        'teacher' => 'End-User Portal',
        'admin', 'staff' => 'Supply Office Portal',
        default => 'PARDS Portal',
    };
    $dashboardRoute = match ($role) {
        'admin' => route('admin.dashboard'),
        'staff' => route('staff.dashboard'),
        'teacher' => route('teacher.dashboard'),
        default => route('dashboard'),
    };

    $links = [
        ['label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill', 'url' => $dashboardRoute, 'visible' => true],
        ['label' => 'Manage Users', 'icon' => 'bi-people-fill', 'url' => route('users.index'), 'visible' => $role === 'admin'],
        ['label' => $role === 'admin' ? 'Manage Property' : 'View Property List', 'icon' => 'bi-box-seam-fill', 'url' => route('properties.index'), 'visible' => in_array($role, ['admin', 'staff'], true)],
        ['label' => 'Inventory History', 'icon' => 'bi-clock-history', 'url' => route('inventory-logs.index'), 'visible' => in_array($role, ['admin', 'staff'], true)],
        ['label' => $role === 'admin' ? 'View Assign Property' : 'Assign Property', 'icon' => 'bi-journal-check', 'url' => $role === 'admin' ? route('assignments.index') : route('assignments.create'), 'visible' => in_array($role, ['admin', 'staff'], true)],
        ['label' => 'Return Logs', 'icon' => 'bi-arrow-return-left', 'url' => route('returns.index'), 'visible' => in_array($role, ['admin', 'staff'], true)],
        ['label' => $role === 'admin' ? 'View Disposal Request' : 'Request Disposal', 'icon' => 'bi-archive-fill', 'url' => $role === 'admin' ? route('disposals.index') : route('disposals.create'), 'visible' => in_array($role, ['admin', 'staff'], true)],
        ['label' => 'Reports', 'icon' => 'bi-bar-chart-fill', 'url' => route('reports.index'), 'visible' => in_array($role, ['admin', 'staff'], true)],
        ['label' => 'QR Codes', 'icon' => 'bi-qr-code-scan', 'url' => route('qr.index'), 'visible' => in_array($role, ['admin', 'staff'], true)],
        ['label' => $role === 'admin' ? 'Approve Property Requests' : 'Property Requests', 'icon' => 'bi-clipboard-plus-fill', 'url' => route('property-requests.index'), 'visible' => in_array($role, ['admin', 'staff'], true)],
        ['label' => 'My Accountabilities', 'icon' => 'bi-backpack4-fill', 'url' => route('teacher.properties.index'), 'visible' => $role === 'teacher'],
        ['label' => 'Property Requests', 'icon' => 'bi-clipboard-plus-fill', 'url' => route('teacher.property-requests.index'), 'visible' => $role === 'teacher'],
        ['label' => 'My Profile', 'icon' => 'bi-person-circle', 'url' => route('profile.edit'), 'visible' => true],
    ];
@endphp

<header class="mobile-app-header d-lg-none d-flex align-items-center justify-content-between px-3 py-2">
    <a href="{{ $dashboardRoute }}" class="text-white text-decoration-none fw-semibold fs-5">
        <i class="bi bi-building-check me-2" aria-hidden="true"></i>PARDS
    </a>
    <button class="btn btn-outline-light d-flex align-items-center gap-2" type="button"
        data-bs-toggle="offcanvas" data-bs-target="#appNavigation" aria-controls="appNavigation" aria-expanded="false" data-navigation-toggle>
        <span class="hamburger-icon" aria-hidden="true"><span></span><span></span><span></span></span>Menu
    </button>
</header>

<aside class="sidebar-shell offcanvas-lg offcanvas-start d-flex flex-column p-3 p-lg-4"
    tabindex="-1" id="appNavigation" aria-labelledby="navigationTitle">
    <div class="d-flex align-items-center justify-content-between d-lg-none mb-3">
        <h2 class="h5 text-white mb-0" id="navigationTitle">Navigation</h2>
        <button type="button" class="btn btn-outline-light" data-bs-dismiss="offcanvas"
            data-bs-target="#appNavigation" aria-label="Close navigation">
            <span class="hamburger-icon" aria-hidden="true"><span></span><span></span><span></span></span>
        </button>
    </div>
    <div class="sidebar-header mb-4 pb-3">
        <a href="{{ $dashboardRoute }}" class="text-decoration-none text-white">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-4 px-3 py-2 bg-white bg-opacity-10">
                    <i class="bi bi-building-check fs-4"></i>
                </div>
                <div>
                    <span class="fs-4 fw-semibold d-block">PARDS</span>
                    <small class="text-white-50">{{ $portalLabel }}</small>
                </div>
            </div>
        </a>
    </div>

    <div class="mb-3 text-uppercase small fw-semibold text-white-50">Navigation</div>

    <ul class="nav nav-pills flex-column gap-2">
        @foreach (array_values(array_filter($links, fn ($link) => $link['visible'])) as $link)
            @if ($link['visible'])
                <li class="nav-item" style="--menu-delay: {{ $loop->index * 35 }}ms;">
                    <a
                        href="{{ $link['url'] }}"
                        class="nav-link {{ request()->url() === $link['url'] ? 'active' : '' }}"
                    >
                        <i class="bi {{ $link['icon'] }}"></i>
                        <span>{{ $link['label'] }}</span>
                    </a>
                </li>
            @endif
        @endforeach
    </ul>

    <div class="mt-auto pt-4">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-outline-light w-100 rounded-4 py-2">
                <i class="bi bi-box-arrow-right me-2"></i>Logout
            </button>
        </form>
    </div>
</aside>
