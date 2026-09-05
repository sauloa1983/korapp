@php
    use App\Models\CompanySetting;

    try {
        $company = CompanySetting::current();
        $sidebarTheme = $company->sidebarThemeToken();
        $primary = $company->primary_color ?: '#3B82F6';
        $sidebarVars = $company->sidebarCssVariables();
    } catch (Throwable) {
        $sidebarTheme = 'light';
        $primary = '#3B82F6';
        $sidebarVars = [
            '--saas-sidebar' => '#FFFFFF',
            '--saas-sidebar-text' => '#1E293B',
            '--saas-sidebar-muted' => '#64748B',
            '--saas-sidebar-hover' => '#F1F5F9',
            '--saas-sidebar-active' => '#F1F5F9',
            '--saas-sidebar-active-text' => '#1E293B',
            '--saas-sidebar-border' => '#E2E8F0',
        ];
    }
@endphp

<style id="korapp-company-theme">
    html.fi {
        --saas-primary: {{ $primary }};
@foreach ($sidebarVars as $property => $value)
        {{ $property }}: {{ $value }} !important;
@endforeach
    }
</style>
<script>
    document.documentElement.setAttribute('data-sidebar-theme', @js($sidebarTheme));
</script>
