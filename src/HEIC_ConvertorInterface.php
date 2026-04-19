<?php

namespace AJUR\Toolkit;

interface HEIC_ConvertorInterface
{
    public function __construct(array $options = []);
    public function setOptions(array $options): self;

    public function isAvailable(): bool;

    public function setQuality(int $quality): self;

    public function setMaxWidth(?int $width): self;

    public function convert(string $inputPath, ?string $outputPath = null): string;

    public function diagnose(string $inputPath): array;

}