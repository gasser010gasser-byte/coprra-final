<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SwitchLanguageRequest;
use App\Http\Requests\SwitchCurrencyRequest;
use App\Models\Currency;
use App\Models\Country;
use App\Models\Language;
use App\Models\UserLocaleSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class LocaleController extends Controller
{
    /**
     * Change application language via GET route: /language/{langCode}
     */
    public function changeLanguage(Request $request, string $langCode): RedirectResponse
    {
        $language = Language::query()->where('code', $langCode)->first();

        // Fallback to supported locales when DB record is missing
        $supported = config('app.supported_locales', ['en']);
        if (!$language && !\in_array($langCode, $supported, true)) {
            return redirect()->back()->with('error', __('Invalid language'));
        }

        $code = $language->code ?? $langCode;
        App::setLocale($code);
        Session::put('locale', $code);

        // Persist user preference when authenticated
        if ($request->user()) {
            $setting = UserLocaleSetting::firstOrNew(['user_id' => $request->user()->id]);
            if ($language) {
                $setting->language_id = $language->id;
            }
            $setting->save();
        }

        return redirect()->back()->with('status', __('Language updated'));
    }

    /**
     * Change application currency via GET route: /currency/{currencyCode}
     */
    public function changeCurrency(Request $request, string $currencyCode): RedirectResponse
    {
        $currency = Currency::query()->where('code', $currencyCode)->first();
        if (!$currency) {
            return redirect()->back()->with('error', __('Invalid currency'));
        }

        Session::put('currency', $currency->code);

        // Persist user preference when authenticated
        if ($request->user()) {
            $setting = UserLocaleSetting::firstOrNew(['user_id' => $request->user()->id]);
            $setting->currency_id = $currency->id;
            $setting->save();
        }

        return redirect()->back()->with('status', __('Currency updated'));
    }

    /**
     * Switch language via POST route: /locale/language
     */
    public function switchLanguage(SwitchLanguageRequest $request): RedirectResponse
    {
        $code = (string) ($request->input('language') ?? $request->input('locale') ?? config('app.locale', 'en'));

        \Log::info('Language switch requested', [
            'code' => $code,
            'input' => $request->all(),
            'user_id' => $request->user()?->id,
            'ip' => $request->ip()
        ]);

        return $this->changeLanguage($request, $code);
    }

    /**
     * Switch currency via POST route: /locale/currency
     */
    public function switchCurrency(SwitchCurrencyRequest $request): RedirectResponse
    {
        $currencyCode = (string) $request->input('currency');

        \Log::info('Currency switch requested', [
            'code' => $currencyCode,
            'input' => $request->all(),
            'user_id' => $request->user()?->id,
            'ip' => $request->ip()
        ]);

        return $this->changeCurrency($request, $currencyCode);
    }

    /**
     * Change application country via GET route: /country/{countryCode}
     */
    public function changeCountry(Request $request, string $countryCode): RedirectResponse
    {
        $country = Country::query()->where('code', $countryCode)->first();
        if (!$country) {
            return redirect()->back()->with('error', __('Invalid country'));
        }

        Session::put('locale_country', $country->code);

        // Persist user preference when authenticated
        if ($request->user()) {
            $setting = UserLocaleSetting::firstOrNew(['user_id' => $request->user()->id]);
            $setting->country_code = $country->code;
            $setting->save();
        }

        return redirect()->back()->with('status', __('Country updated'));
    }

    /**
     * Switch country via POST route: /locale/country
     */
    public function switchCountry(Request $request): RedirectResponse
    {
        $code = (string) ($request->input('country') ?? '');
        return $this->changeCountry($request, $code);
    }
}
