@php
    $primary = $posBranding->primaryColor();
    $secondary = $posBranding->secondaryColor();

    $hexToRgb = static function (string $hex): string {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return "{$r}, {$g}, {$b}";
    };

    $primaryRgb = $hexToRgb($primary);
    $secondaryRgb = $hexToRgb($secondary);
@endphp
<style>
    :root {
        --bw-primary: {{ $primary }};
        --bw-primary-rgb: {{ $primaryRgb }};
        --bw-secondary: {{ $secondary }};
        --bw-secondary-rgb: {{ $secondaryRgb }};
    }
</style>
