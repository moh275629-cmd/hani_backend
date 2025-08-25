<?php

namespace App\Traits;

use Illuminate\Support\Facades\Crypt;

trait Encryptable
{
    /**
     * Get an attribute from the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);

        if (in_array($key, $this->encryptable) && !is_null($value)) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception $e) {
                // If decryption fails, return the original value
                return $value;
            }
        }

        return $value;
    }

    /**
     * Set a given attribute on the model.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return $this
     */
    public function setAttribute($key, $value)
    {
        if (in_array($key, $this->encryptable) && !is_null($value)) {
            $value = Crypt::encryptString($value);
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Get the encrypted attributes for the model.
     *
     * @return array
     */
    public function getEncryptableAttributes()
    {
        return $this->encryptable ?? [];
    }

    /**
     * Check if an attribute is encryptable.
     *
     * @param  string  $key
     * @return bool
     */
    public function isEncryptable($key)
    {
        return in_array($key, $this->encryptable ?? []);
    }

    /**
     * Convert the model instance to an array.
     *
     * @return array
     */
    public function toArray()
    {
        $attributes = parent::toArray();
        
        // Decrypt encrypted attributes for array conversion
        foreach ($this->encryptable as $field) {
            if (isset($attributes[$field]) && !is_null($attributes[$field])) {
                try {
                    $attributes[$field] = \Illuminate\Support\Facades\Crypt::decryptString($attributes[$field]);
                } catch (\Exception $e) {
                    // If decryption fails, keep the original value
                    continue;
                }
            }
        }
        
        return $attributes;
    }

    /**
     * Convert the model instance to JSON.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }


}
