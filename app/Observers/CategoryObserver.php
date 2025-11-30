<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Category;
use Illuminate\Support\Str;

class CategoryObserver
{
    /**
     * Handle the Category "creating" event.
     */
    public function creating(Category $category): void
    {
        // Generate slug if not set
        if (empty($category->slug) && ! empty($category->name)) {
            $baseSlug = Str::slug($category->name);
            $slug = $baseSlug;
            $count = 1;

            // Ensure slug is unique
            while (Category::where('slug', $slug)->where('id', '!=', $category->id ?? 0)->exists()) {
                $slug = "{$baseSlug}-{$count}";
                ++$count;
            }

            $category->slug = $slug;
        }

        // Set level if not set
        if (is_null($category->level)) {
            if ($category->parent_id) {
                $parent = Category::find($category->parent_id);
                $category->level = $parent ? ($parent->level + 1) : 0;
            } else {
                $category->level = 0;
            }
        }
    }

    /**
     * Handle the Category "updating" event.
     */
    public function updating(Category $category): void
    {
        // Update slug if name changed
        if ($category->isDirty('name') && ! empty($category->name)) {
            $baseSlug = Str::slug($category->name);
            $slug = $baseSlug;
            $count = 1;

            // Ensure slug is unique
            while (Category::where('slug', $slug)->where('id', '!=', $category->id)->exists()) {
                $slug = "{$baseSlug}-{$count}";
                ++$count;
            }

            $category->slug = $slug;
        }

        // Update level if parent changed
        if ($category->isDirty('parent_id')) {
            if ($category->parent_id) {
                $parent = Category::find($category->parent_id);
                $category->level = $parent ? ($parent->level + 1) : 0;
            } else {
                $category->level = 0;
            }
        }
    }
}
