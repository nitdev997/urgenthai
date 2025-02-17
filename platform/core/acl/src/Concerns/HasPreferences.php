<?php

namespace Botble\ACL\Concerns;

use Botble\ACL\Models\UserMeta;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasPreferences
{
    public function meta(): HasMany
    {
        return $this->hasMany(UserMeta::class, 'user_id');
    }

    public function setMeta(string $key, mixed $value): bool
    {
        $meta = $this->meta()->firstOrCreate([
            'key' => $key,
        ]);

        return $meta->update(['value' => $value]);
    }

    public function getMeta(string $key, mixed $default = null): mixed
    {
        $meta = $this->meta()
            ->where('key', $key)
            ->select('value')
            ->first();

        if (! empty($meta)) {
            return $meta->value;
        }

        return $default;
    }
}
