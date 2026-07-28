<?php
namespace App\Support;
use App\Models\Item; use App\Models\ItemStock; use App\Models\StockApiSyncRecord; use App\Models\Warehouse; use Illuminate\Support\Carbon;
class StockApiSyncService {
 public static function syncItem(int $itemId, ?Carbon $changedAt=null): void {
  $item=Item::with(['category','bundleComponents.component'])->find($itemId); if(!$item)return;
  $warehouseId=(int)Warehouse::where('code',config('stock_api.source_warehouse_code'))->value('id'); if($warehouseId<=0)return;
  $stock=ItemStock::where('item_id',$item->id)->where('warehouse_id',$warehouseId)->first();
  $qty=$item->isBundle()?BundleService::virtualAvailableQty($item,$warehouseId):(int)($stock?->stock??0);
  $data=['item_id'=>$item->id,'sku'=>$item->sku,'name'=>$item->name,'category'=>$item->category?->name,'uom'=>'pcs','qty'=>max(0,$qty),'min_qty'=>(int)($stock?->safety_stock??$item->safety_stock??0)?:null,'status'=>$item->isActive()?'active':'inactive','source_updated_at'=>$changedAt??now()];
  $record=StockApiSyncRecord::where(fn($q)=>$q->where('item_id',$item->id)->orWhere('sku',$item->sku))->first(); $record?$record->fill($data)->save():StockApiSyncRecord::create($data);
 }
 public static function syncBundlesUsingComponent(int $componentId, ?Carbon $changedAt=null): void { Item::where('item_type',Item::TYPE_BUNDLE)->whereHas('bundleComponents',fn($q)=>$q->where('component_item_id',$componentId))->pluck('id')->each(fn($id)=>self::syncItem((int)$id,$changedAt)); }
 public static function markDeleted(Item $item, ?Carbon $changedAt=null): void { StockApiSyncRecord::where('item_id',$item->id)->each(fn($r)=>$r->update(['item_id'=>null,'qty'=>0,'min_qty'=>null,'status'=>'deleted','source_updated_at'=>$changedAt??now()])); }
}
