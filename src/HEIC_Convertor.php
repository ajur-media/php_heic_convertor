<?php

namespace AJUR\Toolkit;

class HEIC_Convertor
{
    private int $quality = 90;
    private bool $preserveMetadata = true;
    private ?int $maxWidth = null;
    private ?int $maxHeight = null;
    private $logger = null;
    private bool $autoRotate = true;

    public function __construct(array $options = [])
    {
        $this->setOptions($options);
        $this->checkRequirements();
    }

    public function setOptions(array $options): self
    {
        if (isset($options['quality'])) {
            $this->setQuality($options['quality']);
        }

        if (isset($options['preserveMetadata'])) {
            $this->preserveMetadata = (bool)$options['preserveMetadata'];
        }

        if (isset($options['maxWidth'])) {
            $this->setMaxWidth($options['maxWidth']);
        }

        if (isset($options['maxHeight'])) {
            $this->setMaxHeight($options['maxHeight']);
        }

        if (isset($options['autoRotate'])) {
            $this->autoRotate = (bool)$options['autoRotate'];
        }

        if (isset($options['logger']) && is_callable($options['logger'])) {
            $this->logger = $options['logger'];
        }

        return $this;
    }

    private function checkRequirements(): void
    {
        if (!extension_loaded('imagick')) {
            throw new \RuntimeException('Расширение Imagick не установлено');
        }
    }

    public function setQuality(int $quality): self
    {
        if ($quality < 1 || $quality > 100) {
            throw new \InvalidArgumentException('Качество должно быть в диапазоне 1-100');
        }

        $this->quality = $quality;
        return $this;
    }

    public function setMaxWidth(?int $width): self
    {
        $this->maxWidth = $width;
        return $this;
    }

    public function setMaxHeight(?int $height): self
    {
        $this->maxHeight = $height;
        return $this;
    }

    public function convert(string $inputPath, ?string $outputPath = null): string
    {
        $this->validateInputFile($inputPath);

        if ($outputPath === null) {
            $outputPath = $this->generateOutputPath($inputPath);
        }

        $this->ensureOutputDirectory($outputPath);

        $errors = [];

        // Попытка 1: Стандартный метод
        try {
            return $this->convertWithStandardMethod($inputPath, $outputPath);
        } catch (\Exception $e) {
            $errors[] = "Standard method: " . $e->getMessage();
            $this->log("Стандартный метод не сработал: " . $e->getMessage(), 'warning');
        }

        // Попытка 2: Метод с обходом геометрии
        try {
            return $this->convertWithoutGeometry($inputPath, $outputPath);
        } catch (\Exception $e) {
            $errors[] = "No geometry method: " . $e->getMessage();
            $this->log("Метод без геометрии не сработал: " . $e->getMessage(), 'warning');
        }

        // Попытка 3: CLI метод
        if ($this->isImagickCliAvailable()) {
            try {
                return $this->convertWithCli($inputPath, $outputPath);
            } catch (\Exception $e) {
                $errors[] = "CLI method: " . $e->getMessage();
                $this->log("CLI метод не сработал: " . $e->getMessage(), 'warning');
            }
        }

        throw new \RuntimeException(
            "Не удалось конвертировать HEIC в JPEG. Ошибки:\n" . implode("\n", $errors)
        );
    }

    /**
     * Стандартный метод конвертации
     */
    private function convertWithStandardMethod(string $inputPath, string $outputPath): string
    {
        $imagick = new \Imagick();

        try {
            $imagick->readImage($inputPath);

            // Исправление геометрии (безопасная версия)
            $this->fixImageGeometrySafe($imagick);

            // Изменение размера
            if ($this->maxWidth !== null || $this->maxHeight !== null) {
                $this->smartResize($imagick);
            }

            // Автоповорот
            if ($this->autoRotate && method_exists($imagick, 'autoRotateImage')) {
                $imagick->autoRotateImage();
            }

            // Настройки JPEG
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompression(\Imagick::COMPRESSION_JPEG);
            $imagick->setImageCompressionQuality($this->quality);

            // Цветовое пространство
            $this->fixColorSpace($imagick);

            // Метаданные
            if (!$this->preserveMetadata) {
                $imagick->stripImage();
            }

            $imagick->writeImage($outputPath);
            $imagick->clear();
            $imagick->destroy();

            if (!file_exists($outputPath)) {
                throw new \RuntimeException('Не удалось сохранить JPEG файл');
            }

            return $outputPath;

        } finally {
            if (isset($imagick)) {
                $imagick->clear();
                $imagick->destroy();
            }
        }
    }

    /**
     * Метод конвертации без манипуляций с геометрией
     */
    private function convertWithoutGeometry(string $inputPath, string $outputPath): string
    {
        $imagick = new \Imagick();

        try {
            $imagick->readImage($inputPath);

            // Просто меняем формат, не трогая геометрию
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompressionQuality($this->quality);

            // Минимальные настройки
            $imagick->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);

            $imagick->writeImage($outputPath);
            $imagick->clear();
            $imagick->destroy();

            if (!file_exists($outputPath)) {
                throw new \RuntimeException('Не удалось сохранить JPEG файл');
            }

            return $outputPath;

        } finally {
            if (isset($imagick)) {
                $imagick->clear();
                $imagick->destroy();
            }
        }
    }

    /**
     * Безопасное исправление геометрии (работает во всех версиях Imagick)
     */
    private function fixImageGeometrySafe(\Imagick $imagick): void
    {
        try {
            $width = $imagick->getImageWidth();
            $height = $imagick->getImageHeight();

            if ($width <= 0 || $height <= 0) {
                return;
            }

            // Пробуем разные методы в зависимости от версии
            if (method_exists($imagick, 'resetImagePage')) {
                try {
                    // Версия с аргументом
                    $imagick->resetImagePage($width . 'x' . $height . '+0+0');
                } catch (\ArgumentCountError $e) {
                    // Старая версия без аргументов
                    $imagick->resetImagePage($width . 'x' . $height . '+0+0');
                } catch (\Exception $e) {
                    // Если не сработало, используем setImagePage
                    $imagick->setImagePage($width, $height, 0, 0);
                }
            } else {
                $imagick->setImagePage($width, $height, 0, 0);
            }

        } catch (\Exception $e) {
            $this->log("Ошибка при исправлении геометрии: " . $e->getMessage(), 'warning');
            // Не выбрасываем исключение, продолжаем без исправления геометрии
        }
    }

    /**
     * Умное изменение размера
     *
     * @throws \ImagickException
     */
    private function smartResize(\Imagick $imagick): void
    {
        $width = $imagick->getImageWidth();
        $height = $imagick->getImageHeight();

        if ($width <= 0 || $height <= 0) {
            return;
        }

        $targetWidth = $this->maxWidth ?? $width;
        $targetHeight = $this->maxHeight ?? $height;

        $ratio = min($targetWidth / $width, $targetHeight / $height);
        $newWidth = max(1, (int)($width * $ratio));
        $newHeight = max(1, (int)($height * $ratio));

        if ($newWidth !== $width || $newHeight !== $height) {
            $imagick->resizeImage($newWidth, $newHeight, \Imagick::FILTER_LANCZOS, 1);
        }
    }

    /**
     * Исправление цветового пространства
     */
    private function fixColorSpace(\Imagick $imagick): void
    {
        try {
            $imagick->setImageColorspace(\Imagick::COLORSPACE_SRGB);
        } catch (\Exception $e) {
            // Игнорируем ошибки цветового пространства
        }
    }

    /**
     * CLI метод
     */
    private function convertWithCli(string $inputPath, string $outputPath): string
    {
        $cmd = sprintf(
            'convert "%s" -quality %d -format jpg "%s" 2>&1',
            $inputPath,
            $this->quality,
            $outputPath
        );

        if ($this->maxWidth !== null || $this->maxHeight !== null) {
            $size = '';
            if ($this->maxWidth !== null && $this->maxHeight !== null) {
                $size = sprintf('-resize %dx%d\\>', $this->maxWidth, $this->maxHeight);
            } elseif ($this->maxWidth !== null) {
                $size = sprintf('-resize %dx', $this->maxWidth);
            } else {
                $size = sprintf('-resize x%d', $this->maxHeight);
            }
            $cmd = str_replace('-quality', $size . ' -quality', $cmd);
        }

        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($outputPath)) {
            throw new \RuntimeException("CLI конвертация не удалась: " . implode("\n", $output));
        }

        return $outputPath;
    }

    /**
     * Проверка доступности CLI
     */
    private function isImagickCliAvailable(): bool
    {
        $output = [];
        exec('convert -version 2>&1', $output, $returnCode);
        return $returnCode === 0;
    }

    /**
     * Валидация входного файла
     */
    private function validateInputFile(string $inputPath): void
    {
        if (!file_exists($inputPath)) {
            throw new \InvalidArgumentException("Файл не найден: {$inputPath}");
        }

        if (!is_readable($inputPath)) {
            throw new \InvalidArgumentException("Файл недоступен для чтения: {$inputPath}");
        }
    }

    /**
     * Создание выходной директории
     */
    private function ensureOutputDirectory(string $outputPath): void
    {
        $directory = dirname($outputPath);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    /**
     * Генерация пути для выходного файла
     */
    private function generateOutputPath(string $inputPath): string
    {
        $directory = dirname($inputPath);
        $filename = pathinfo($inputPath, PATHINFO_FILENAME);

        return $directory . '/' . $filename . '_' . time() . '.jpg';
    }

    /**
     * Логирование
     */
    private function log(string $message, string $level = 'info'): void
    {
        if ($this->logger !== null) {
            ($this->logger)($message, $level, $this);
        }
    }

    /**
     * Диагностика
     */
    public function diagnose(string $inputPath): array
    {
        $diagnosis = [
            'file_exists' => file_exists($inputPath),
            'file_size' => file_exists($inputPath) ? filesize($inputPath) : 0,
            'php_version' => PHP_VERSION,
            'imagick_loaded' => extension_loaded('imagick'),
        ];

        if (extension_loaded('imagick')) {
            $version = (new \Imagick())->getVersion();
            $diagnosis['imagick_version'] = $version['versionString'] ?? 'unknown';
            $diagnosis['heic_supported'] = in_array('HEIC', \Imagick::queryFormats());
        }

        if (file_exists($inputPath)) {
            try {
                $imagick = new \Imagick();
                $imagick->pingImage($inputPath);
                $diagnosis['width'] = $imagick->getImageWidth();
                $diagnosis['height'] = $imagick->getImageHeight();
                $diagnosis['format'] = $imagick->getImageFormat();
                $imagick->destroy();
            } catch (\Exception $e) {
                $diagnosis['ping_error'] = $e->getMessage();
            }
        }

        return $diagnosis;
    }

}