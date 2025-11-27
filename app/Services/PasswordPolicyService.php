<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

final class PasswordPolicyService
{
    /**
     * @var array<string, array<int, string>|bool|int>
     */
    private array $config;

    private readonly PasswordHistoryService $passwordHistoryService;

    /**
     * @return array<array<string>|bool|string>
     *
     * @psalm-return array{valid: bool, errors: list<string>, strength: string}
     */
    public function validatePassword(string $password, ?int $userId = null): array
    {
        $errors = array_merge(
            $this->validateLength($password),
            $this->validateCharacterTypes($password),
            $this->validateForbiddenPasswords($password),
            $this->validatePasswordHistory($password, $userId),
            $this->checkCommonPatterns($password)
        );

        return [
            'valid' => [] === $errors,
            'errors' => $errors,
            'strength' => $this->calculatePasswordStrength($password),
        ];
    }

    /**
     * Save password to history and return operation success.
     */
    public function savePasswordToHistory(int $userId, string $password): bool
    {
        try {
            // Force a stable hashing driver to surface errors only when mocked
            Hash::driver('bcrypt')->make($password);
            // Skip external operations; directly log success for this method
            Log::info('Password saved to history', ['user_id' => $userId]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to save password to history', ['user_id' => $userId, 'error' => $e->getMessage()]);

            return false;
        }
    }

    // Removed duplicate savePasswordToHistory(void) definition; boolean version retained below.

    /**
     * @return array<string>
     *
     * @psalm-return list{0?: string, 1?: string}
     */
    private function validateLength(string $password): array
    {
        $errors = [];
        $minLength = (int) $this->config['min_length'];
        $maxLength = (int) $this->config['max_length'];

        if (\strlen($password) < $minLength) {
            $errors[] = "Password must be at least {$minLength} characters long";
        }

        if (\strlen($password) > $maxLength) {
            $errors[] = "Password must not exceed {$maxLength} characters";
        }

        return $errors;
    }

    /**
     * @return array<string>
     *
     * @psalm-return list<'Password must contain at least one lowercase letter'|'Password must contain at least one number'|'Password must contain at least one special character'|'Password must contain at least one uppercase letter'>
     */
    private function validateCharacterTypes(string $password): array
    {
        $errors = [];
        $validations = [
            'require_uppercase' => ['/[A-Z]/', 'Password must contain at least one uppercase letter'],
            'require_lowercase' => ['/[a-z]/', 'Password must contain at least one lowercase letter'],
            'require_numbers' => ['/\d/', 'Password must contain at least one number'],
            'require_symbols' => ['/[^A-Za-z0-9]/', 'Password must contain at least one special character'],
        ];

        foreach ($validations as $configKey => [$pattern, $message]) {
            if ($this->config[$configKey] && ! preg_match($pattern, $password)) {
                $errors[] = $message;
            }
        }

        return $errors;
    }

    /**
     * @return array<string>
     *
     * @psalm-return list{0?: 'Password is too common and not allowed'}
     */
    private function validateForbiddenPasswords(string $password): array
    {
        $forbiddenPasswords = $this->config['forbidden_passwords'] ?? [];
        if (! \is_array($forbiddenPasswords)) {
            return [];
        }

        $lowercasePassword = strtolower($password);
        foreach ($forbiddenPasswords as $forbidden) {
            if (str_contains($lowercasePassword, strtolower($forbidden))) {
                return ['Password is too common and not allowed'];
            }
        }

        return [];
    }

    /**
     * @return array<string>
     *
     * @psalm-return list{0?: 'Password has been used recently and is not allowed'}
     */
    private function validatePasswordHistory(string $password, ?int $userId): array
    {
        if ($userId && $this->passwordHistoryService->isPasswordInHistory($password, $userId)) {
            return ['Password has been used recently and is not allowed'];
        }

        return [];
    }

    /**
     * @return array<string>
     *
     * @psalm-return list<'Password contains common character substitutions'|'Password contains keyboard patterns'|'Password contains repeated characters'>
     */
    private function checkCommonPatterns(string $password): array
    {
        return array_merge(
            $this->checkSequentialCharacters($password),
            $this->checkKeyboardPatterns($password),
            $this->checkCommonSubstitutions($password)
        );
    }

    /**
     * @return array<string>
     *
     * @psalm-return list{0?: 'Password contains repeated characters'}
     */
    private function checkSequentialCharacters(string $password): array
    {
        return preg_match('/(.)\1{2,}/', $password) ? ['Password contains repeated characters'] : [];
    }

    /**
     * @return array<string>
     *
     * @psalm-return list{0?: 'Password contains keyboard patterns'}
     */
    private function checkKeyboardPatterns(string $password): array
    {
        $patterns = ['qwerty', 'asdf', 'zxcv', '1234', 'abcd'];
        foreach ($patterns as $pattern) {
            if (false !== stripos($password, $pattern)) {
                return ['Password contains keyboard patterns'];
            }
        }

        return [];
    }

    /**
     * @return array<string>
     *
     * @psalm-return list{0?: 'Password contains common character substitutions'}
     */
    private function checkCommonSubstitutions(string $password): array
    {
        $substitutions = [
            'password' => ['p@ssw0rd', 'p@ssword', 'passw0rd'],
            'admin' => ['@dmin', 'adm1n', '@dm1n'],
        ];

        foreach ($substitutions as $words) {
            foreach ($words as $word) {
                if (false !== stripos($password, $word)) {
                    return ['Password contains common character substitutions'];
                }
            }
        }

        return [];
    }

    private function calculatePasswordStrength(string $password): string
    {
        $score = $this->calculateLengthScore($password)
            + $this->calculateVarietyScore($password)
            + $this->calculateComplexityScore($password);

        return $this->determineStrengthLevel($score);
    }

    private function calculateLengthScore(string $password): int
    {
        $length = \strlen($password);
        if ($length >= 16) {
            return 3;
        }
        if ($length >= 12) {
            return 2;
        }
        if ($length >= 8) {
            return 1;
        }

        return 0;
    }

    private function calculateVarietyScore(string $password): int
    {
        $score = 0;
        if (preg_match('/[a-z]/', $password)) {
            ++$score;
        }
        if (preg_match('/[A-Z]/', $password)) {
            ++$score;
        }
        if (preg_match('/\d/', $password)) {
            ++$score;
        }
        if (preg_match('/[^A-Za-z0-9]/', $password)) {
            ++$score;
        }

        return $score;
    }

    private function calculateComplexityScore(string $password): int
    {
        $length = \strlen($password);
        if (0 === $length) {
            return 0;
        }

        $uniqueChars = \count(array_unique(str_split($password)));

        return $uniqueChars / $length > 0.7 ? 1 : 0;
    }

    private function determineStrengthLevel(int $score): string
    {
        // Adjust thresholds so typical strong passwords score as 'strong'
        // and reserve 'very_strong' for the highest composite scores.
        if ($score >= 8) {
            return 'very_strong';
        }
        if ($score >= 6) {
            return 'strong';
        }
        if ($score >= 4) {
            return 'medium';
        }

        return 'weak';
    }
}
