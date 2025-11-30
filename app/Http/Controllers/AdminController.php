<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AdminController extends Controller
{
    public function dashboard(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! method_exists($user, 'hasRole') || ! $user->hasRole('admin')) {
            return redirect()->route('home');
        }

        $stats = [
            'users' => User::query()->count(),
            'products' => Product::query()->count(),
            'stores' => Store::query()->count(),
            'categories' => Category::query()->count(),
            'brands' => Brand::query()->count(),
        ];

        $recentUsers = User::query()->latest()->take(5)->get();
        $recentProducts = Product::query()->latest()->take(5)->get();

        return view('admin.index', [
            'stats' => $stats,
            'recentUsers' => $recentUsers,
            'recentProducts' => $recentProducts,
        ]);
    }

    public function users(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! method_exists($user, 'hasRole') || ! $user->hasRole('admin')) {
            return redirect()->route('home');
        }

        $users = User::query()->latest()->paginate(15);

        return view('admin.users', [
            'users' => $users,
        ]);
    }

    public function createUser(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        // Only admin and super_admin can create users (not moderator)
        if (! $user || ! method_exists($user, 'hasRole')) {
            abort(403, 'Access denied. Admin privilege required.');
        }

        // Check if user is admin or super_admin (not moderator)
        $isAdmin = $user->hasRole('admin') || $user->hasRole('super_admin');
        if (! $isAdmin) {
            abort(403, 'Access denied. Admin privilege required.');
        }

        return view('admin.users.create');
    }

    public function products(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! method_exists($user, 'hasRole') || ! $user->hasRole('admin')) {
            return redirect()->route('home');
        }

        $products = Product::query()
            ->with(['brand', 'category'])
            ->latest()
            ->paginate(20);

        return view('admin.products.index', [
            'products' => $products,
        ]);
    }

    public function brands(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! method_exists($user, 'hasRole') || ! $user->hasRole('admin')) {
            return redirect()->route('home');
        }

        $brands = Brand::query()->latest()->paginate(20);

        return view('admin.brands.index', [
            'brands' => $brands,
        ]);
    }

    public function categories(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! method_exists($user, 'hasRole') || ! $user->hasRole('admin')) {
            return redirect()->route('home');
        }

        $categories = Category::query()
            ->withCount('products')
            ->latest()
            ->paginate(20);

        return view('admin.categories.index', [
            'categories' => $categories,
        ]);
    }

    public function stores(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! method_exists($user, 'hasRole') || ! $user->hasRole('admin')) {
            return redirect()->route('home');
        }

        $stores = Store::query()->latest()->paginate(20);

        return view('admin.stores', [
            'stores' => $stores,
        ]);
    }

    public function toggleUserAdmin(Request $request, User $user): RedirectResponse
    {
        $acting = $request->user();
        if (! $acting || ! method_exists($acting, 'hasRole') || ! $acting->hasRole('admin')) {
            return redirect()->route('home');
        }

        $user->is_admin = ! (bool) $user->is_admin;
        if ($user->is_admin) {
            $user->role = 'admin';
        } elseif ($user->role === 'admin') {
            $user->role = 'user';
        }

        $user->save();

        return redirect()->route('admin.users')->with('status', 'User admin status updated.');
    }

    public function editProduct(Request $request, Product $product): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! method_exists($user, 'hasRole') || ! $user->hasRole('admin')) {
            return redirect()->route('home');
        }

        return view('admin.products.edit', [
            'product' => $product,
        ]);
    }

    public function updateProduct(Request $request, Product $product): RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! method_exists($user, 'hasRole') || ! $user->hasRole('admin')) {
            return redirect()->route('home');
        }

        // Placeholder: edit functionality will be implemented later
        return redirect()->route('admin.products.edit', $product)->with('status', 'Update endpoint ready. Editing to be implemented.');
    }

    public function editCategory(Request $request, Category $category): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! method_exists($user, 'hasRole') || ! $user->hasRole('admin')) {
            return redirect()->route('home');
        }

        return view('admin.categories.edit', [
            'category' => $category,
        ]);
    }

    public function updateCategory(Request $request, Category $category): RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! method_exists($user, 'hasRole') || ! $user->hasRole('admin')) {
            return redirect()->route('home');
        }

        // Placeholder: edit functionality will be implemented later
        return redirect()->route('admin.categories.edit', $category)->with('status', 'Update endpoint ready. Editing to be implemented.');
    }

    public function systemSettings(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated');
        }

        // Check if user has system.settings permission (only super_admin should have this)
        // For now, check if role is super_admin
        $hasPermission = $user->role === 'super_admin' ||
            (is_object($user->role) && method_exists($user->role, 'hasPermission') && $user->role->hasPermission('system.settings'));

        if (! $hasPermission) {
            abort(403, 'Access denied. System settings permission required.');
        }

        return view('admin.system-settings', [
            'settings' => [],
        ]);
    }
}
