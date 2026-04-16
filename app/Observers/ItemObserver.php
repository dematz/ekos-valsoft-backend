<?php

namespace App\Observers;

use App\Models\Item;

class ItemObserver
{
    public function saving(Item $item): void
    {
        if ($item->isDirty('quantity') || ! $item->exists) {
            $item->status = $item->quantity <= $item->min_stock_threshold
                ? 'low stock'
                : 'in stock';
        }
    }
}
