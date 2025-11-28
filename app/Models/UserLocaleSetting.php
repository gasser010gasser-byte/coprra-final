<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserLocaleSettingFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $id
 * @property int|null    $user_id
 * @property string|null $session_id
 * @property int|null    $language_id
 * @property int|null    $currency_id
 * @property string|null $ip_address
 * @property string|null $country_code
 * @property User|null   $user
 * @property Language    $language
 * @property Currency    $currency
 *
 * @method static UserLocaleSettingFactory factory(...$parameters)
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
class UserLocaleSetting extends Model
{
    use HasFactory;

    /**
     * @var class-string<Factory<UserLocaleSetting>>
     */
    protected static $factory = UserLocaleSettingFactory::class;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'session_id',
        'language_id',
        'currency_id',
        'ip_address',
        'country_code',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'user_id' => 'integer',
        'language_id' => 'integer',
        'currency_id' => 'integer',
    ];
}
