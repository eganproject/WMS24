<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;

    public const TYPE_SINGLE = 'single';
    public const TYPE_BUNDLE = 'bundle';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'sku',
        'name',
        'item_type',
        'status',
        'category_id',
        'area_id',
        'location_id',
        'main_location_id',
        'secondary_location_id',
        'main_secondary_location_id',
        'address',
        'description',
        'safety_stock',
        'koli_qty',
        'weight_per_pcs',
        'weight_per_koli',
        'cbm_per_koli',
        'length_per_koli',
        'width_per_koli',
        'height_per_koli',
    ];

    protected $casts = [
        'safety_stock' => 'integer',
        'koli_qty' => 'integer',
        'weight_per_pcs' => 'decimal:3',
        'weight_per_koli' => 'decimal:3',
        'cbm_per_koli' => 'decimal:6',
        'length_per_koli' => 'decimal:2',
        'width_per_koli' => 'decimal:2',
        'height_per_koli' => 'decimal:2',
    ];

    /**
     * Volume per koli disimpan di kolom cbm_per_koli dalam m³ (6 desimal = presisi 1 cm³),
     * tetapi ditampilkan dan diinput dalam cm³.
     */
    public const CM3_PER_M3 = 1000000;
    public const MAX_VOLUME_CM3 = 999999999999;

    public function volumePerKoliCm3(): ?int
    {
        return $this->cbm_per_koli === null ? null : (int) round((float) $this->cbm_per_koli * self::CM3_PER_M3);
    }

    public const DIMENSION_FIELDS = ['length_per_koli', 'width_per_koli', 'height_per_koli'];
    public const MAX_DIMENSION_CM = 999999.99;

    /**
     * Volume (cm³) dari panjang × lebar × tinggi. Null jika salah satu dimensi kosong.
     */
    public static function volumeFromDimensions(float|int|string|null $length, float|int|string|null $width, float|int|string|null $height): ?float
    {
        foreach ([$length, $width, $height] as $value) {
            if ($value === null || $value === '') {
                return null;
            }
        }

        return (float) $length * (float) $width * (float) $height;
    }

    public function dimensionsLabel(): string
    {
        $values = array_map(fn (string $field) => $this->{$field}, self::DIMENSION_FIELDS);
        if (in_array(null, $values, true)) {
            return '';
        }
        $format = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');

        return implode(' × ', array_map($format, $values)).' cm';
    }

    public static function cm3ToCbm(float|int|string|null $cm3): ?string
    {
        if ($cm3 === null || $cm3 === '') {
            return null;
        }

        return number_format(round((float) $cm3) / self::CM3_PER_M3, 6, '.', '');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function stock()
    {
        return $this->hasOne(ItemStock::class, 'item_id')
            ->where('warehouse_id', \App\Support\WarehouseService::defaultWarehouseId());
    }

    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function stocks()
    {
        return $this->hasMany(ItemStock::class, 'item_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    /**
     * Bin location di gudang besar. Relasi `location` tetap menjadi lokasi gudang display
     * (dipakai picking list, area picker, dsb).
     */
    public function mainLocation()
    {
        return $this->belongsTo(Location::class, 'main_location_id');
    }

    public function mainAddress(): string
    {
        return (string) ($this->mainLocation?->code ?? '');
    }

    /**
     * Alamat 2 di gudang display (opsional, hanya informasi).
     */
    public function secondaryLocation()
    {
        return $this->belongsTo(Location::class, 'secondary_location_id');
    }

    /**
     * Alamat 2 di gudang besar (opsional, hanya informasi).
     */
    public function mainSecondaryLocation()
    {
        return $this->belongsTo(Location::class, 'main_secondary_location_id');
    }

    public function secondaryAddress(): string
    {
        return (string) ($this->secondaryLocation?->code ?? '');
    }

    public function mainSecondaryAddress(): string
    {
        return (string) ($this->mainSecondaryLocation?->code ?? '');
    }

    /**
     * Alamat gudang display untuk ditampilkan: "Alamat 1 / Alamat 2" bila alamat 2 ada.
     */
    public function displayAddressLabel(): string
    {
        return self::joinAddresses($this->resolvedAddress(), $this->secondaryAddress());
    }

    public function mainAddressLabel(): string
    {
        return self::joinAddresses($this->mainAddress(), $this->mainSecondaryAddress());
    }

    public static function joinAddresses(string ...$addresses): string
    {
        $filtered = array_values(array_unique(array_filter(array_map('trim', $addresses), fn ($a) => $a !== '')));

        return implode(' / ', $filtered);
    }

    public function resolvedArea(): ?Area
    {
        return $this->location?->area ?: $this->area;
    }

    public function resolvedAddress(): string
    {
        return $this->location?->code ?? (string) ($this->address ?? '');
    }

    public function bundleComponents()
    {
        return $this->hasMany(ItemBundleComponent::class, 'bundle_item_id');
    }

    public function bundleParents()
    {
        return $this->hasMany(ItemBundleComponent::class, 'component_item_id');
    }

    public function barcodes()
    {
        return $this->hasMany(ItemBarcode::class, 'item_id');
    }

    public function isBundle(): bool
    {
        return (string) $this->item_type === self::TYPE_BUNDLE;
    }

    public function isSingle(): bool
    {
        return !$this->isBundle();
    }

    public function isActive(): bool
    {
        return ($this->status ?: self::STATUS_ACTIVE) === self::STATUS_ACTIVE;
    }
}
