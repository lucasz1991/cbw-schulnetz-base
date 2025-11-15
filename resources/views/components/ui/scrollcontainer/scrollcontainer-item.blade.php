@props([
  'item' => null,             // optional: übergeben für auto key
  'axis' => 'y',              // 'y' | 'x'
  'snap' => 'start',          // 'none' | 'start' | 'center' | 'end' | 'always'
  'itemClass' => '',
  'minItemWidthClass' => null,// nur bei axis='x'
  'keyPattern' => null,       // z.B. '{id}-{klassen_id}'
  'keyField' => 'id',         // fallback falls keyPattern null
])

@php
  $isY = $axis === 'y';
  $sizeLock = $isY ? '' : ($minItemWidthClass ?? 'min-w-[280px]');
  $snapChild = $snap === 'none' ? '' : 'snap-'.$snap;

  // Wire-Key bauen (nur wenn item übergeben)
  $wireKey = null;
  if (!is_null($item)) {
      $makeKey = function ($val) use ($keyPattern, $keyField) {
          if (is_string($keyPattern) && str_contains($keyPattern, '{')) {
              $out = preg_replace_callback('/\{([^}]+)\}/', function($m) use ($val) {
                  $k = $m[1];
                  if (is_array($val) && array_key_exists($k, $val) && is_scalar($val[$k])) return (string)$val[$k];
                  if (is_object($val) && isset($val->{$k}) && is_scalar($val->{$k})) return (string)$val->{$k};
                  return '';
              }, $keyPattern);
              $out = trim($out, '-_ ');
              if ($out !== '') return $out;
          }
          // fallback: keyField
          if (is_array($val) && array_key_exists($keyField, $val) && is_scalar($val[$keyField])) return (string)$val[$keyField];
          if (is_object($val) && isset($val->{$keyField}) && is_scalar($val->{$keyField})) return (string)$val->{$keyField};
          // letzter fallback: random
          return uniqid('sc-item-', true);
      };
      $wireKey = 'sc-item-'.md5($makeKey($item));
  }
@endphp

<div class="{{ $snapChild }} {{ $itemClass }} {{ $sizeLock }}" @if($wireKey) wire:key="{{ $wireKey }}" @endif>
  {{ $slot }}
</div>
