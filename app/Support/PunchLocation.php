<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Where a punch happened, as reported by the browser or a mobile client.
 *
 * Coordinates travel together with the accuracy the device claimed, so a punch
 * recorded from a rough network fix can be told apart from a precise GPS one.
 */
final class PunchLocation
{
    public function __construct(
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly ?int $accuracy = null,
        public readonly ?string $label = null,
    ) {}

    /** Build from a validated request payload. */
    public static function fromRequest(Request $request): self
    {
        $latitude = $request->filled('latitude') ? (float) $request->input('latitude') : null;
        $longitude = $request->filled('longitude') ? (float) $request->input('longitude') : null;

        return new self(
            latitude: $latitude,
            longitude: $longitude,
            accuracy: $request->filled('accuracy') ? (int) round((float) $request->input('accuracy')) : null,
            label: $request->filled('location')
                ? substr((string) $request->input('location'), 0, 255)
                : null,
        );
    }

    public static function none(): self
    {
        return new self;
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /** A short human-readable form, used when no place name was supplied. */
    public function describe(): ?string
    {
        if ($this->label) {
            return $this->label;
        }

        if (! $this->hasCoordinates()) {
            return null;
        }

        return sprintf('%.5f, %.5f', $this->latitude, $this->longitude);
    }

    /**
     * The attendance columns for one side of the day.
     *
     * @return array<string, mixed>
     */
    public function columns(string $prefix): array
    {
        return [
            $prefix.'_location' => $this->describe(),
            $prefix.'_latitude' => $this->latitude,
            $prefix.'_longitude' => $this->longitude,
            $prefix.'_accuracy' => $this->accuracy,
        ];
    }
}
