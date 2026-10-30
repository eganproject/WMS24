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
    ];

    protected $casts = [
        'safety_stock' => 'integer',
        'koli_qty' => 'integer',
        'weight_per_pcs' => 'decimal:3',
        'weight_per_koli' => 'decimal:3',
        'cbm_per_koli' => 'decimal:6',
    ];

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
