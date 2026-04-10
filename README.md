# php_heic_convertor

Библиотека для конвертации HEIC изображений в JPEG

Требования:
- PHP 8.0+
- Установленный ImageMagick с поддержкой HEIC
- Установленный пакет libheif

- Установка зависимостей в Ubuntu/Debian:
```
sudo apt-get install imagemagick libheif-dev
```

Возможно: `sudo apt-get install imagemagick-6.q16-heic`

Установка в MacOS:
```
brew install imagemagick libheif
```

Включение поддержки HEIC в ImageMagick:

```
identify -list format | grep HEIC
```
