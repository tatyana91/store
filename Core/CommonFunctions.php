<?php
namespace Core;

class CommonFunctions {
    /**
     * Транслитерировать строку
     * @param string
     * @return string
     */
    public static function translit($input){
        $input = mb_strtolower($input,'UTF-8');
        $assoc = array(
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g',
            'д'=>'d','е'=>'e','ё'=>'e','ж'=>'j',
            'з'=>'z','и'=>'i','й'=>'j','к'=>'k',
            'л'=>'l','м'=>'m','н'=>'n','о'=>'o',
            'п'=>'p','р'=>'r','с'=>'s','т'=>'t',
            'у'=>'y','ф'=>'f','х'=>'h','ц'=>'c',
            'ч'=>'ch','ш'=>'sh','щ'=>'sh','ы'=>'y',
            'э'=>'e','ю'=>'u','я'=>'ya',
            'А'=>'A','Б'=>'B','В'=>'V','Г'=>'G',
            'Д'=>'D','Е'=>'E','Ё'=>'E','Ж'=>'J',
            'З'=>'Z','И'=>'I','Й'=>'J','К'=>'K',
            'Л'=>'L','М'=>'M','Н'=>'N','О'=>'O',
            'П'=>'P','Р'=>'R','С'=>'S','Т'=>'T',
            'У'=>'Y','Ф'=>'F','Х'=>'H','Ц'=>'C',
            'Ч'=>'Ch','Ш'=>'Sh','Щ'=>'Sh','Ы'=>'Y',
            'Э'=>'E','Ю'=>'U','Я'=>'Ya',
            'ь'=>'','Ь'=>'','ъ'=>'','Ъ'=>'',' '=>'_',
            '.'=>'', ','=>'_', '('=>'', ')'=>'',
            '\''=>'', '"'=>''
        );
        return strtr($input, $assoc);
    }

    public static function cropImage($path, $path_new, $target_width, $target_height, $swappable = false){
        // Доп.проверка на положительные значения
        if (($target_width < 0) || ($target_height < 0)) {
            return false;
        }

        // Массив с поддерживаемыми типами изображений
        $allowed_extensions = array(1 => "gif", 2 => "jpeg", 3 => "png");

        // Получаем размеры и тип изображения в виде числа
        list($width, $height, $extension_id) = getimagesize($path);

        // Проверяется, нужно ли переворачивать местами размеры изображения
        if ($swappable) {
            if ($width < $height) {
                $swap_dimensions  = $target_width;
                $target_width  = $target_height;
                $target_height = $swap_dimensions;
            }
        }

        if (!array_key_exists($extension_id, $allowed_extensions)) {
            return false;
        }
        $image_extension = $allowed_extensions[$extension_id];

        // Получаем название функции, соответствующую типу, для создания изображения
        $func = 'imagecreatefrom' . $image_extension;
        // Исходное изображение
        $image = $func($path);

        // Определяем отображаемую область
        $cropped_image_width     = 0;
        $cropped_image_height    = 0;
        $horizonatal_crop = 0;
        $vertical_crop = 0;

        if ($target_width / $target_height > $width / $height) {
            $cropped_image_width	= floor($width);
            $cropped_image_height	= floor($width * $target_height / $target_width);
            $vertical_crop			= floor(($height - $cropped_image_height) / 2);
        } else {
            $cropped_image_width	= floor($height * $target_width / $target_height);
            $cropped_image_height	= floor($height);
            $horizonatal_crop		= floor(($width - $cropped_image_width) / 2);
        }

        // Создаём выходное изображение
        $new_image = imagecreatetruecolor($target_width, $target_height);

        // Расширение до альфа-канала для png
        if ($extension_id == 3) {
            $transparent = imagecolorallocatealpha($new_image, 0, 0, 0, 127);
            imagefill($new_image, 0, 0, $transparent);
            imagesavealpha($new_image, true); // Обязательно сохраняем
        }

        imagecopyresampled($new_image, $image, 0, 0, $horizonatal_crop, $vertical_crop, $target_width, $target_height, $cropped_image_width, $cropped_image_height);
        $func = 'image' . $image_extension;

        $answer = $func($new_image, $path_new);

        // Очистка памяти
        if (!imagedestroy($image) || !imagedestroy($new_image)) {
            return false;
        }

        // сохраняем полученное изображение в указанный файл
        return $answer;
    }

    /**
     * @param $src
     * @param $dest
     * @param $width
     * @param $height
     * @param int $rgb
     * @param int $quality
     * @return bool
     */
    public static function resizeImage($src, $dest, $width, $height, $rgb = 0xFFFFFF, $quality = 100) {
        if (!file_exists($src)) {return false;}
        $size = getimagesize($src);
        if ($size === false) {return false;}
        $format = strtolower(substr($size['mime'], strpos($size['mime'], '/') + 1));
        $icfunc = 'imagecreatefrom'.$format;
        if (!function_exists($icfunc)) {return false;}
        $x_ratio = $width  / $size[0];
        $y_ratio = $height / $size[1];
        if ($height == 0) {
            $y_ratio = $x_ratio;
            $height  = $y_ratio * $size[1];
        } elseif ($width == 0) {
            $x_ratio = $y_ratio;
            $width   = $x_ratio * $size[0];
        }
        $ratio       = min($x_ratio, $y_ratio);
        $use_x_ratio = ($x_ratio == $ratio);
        $new_width   = $use_x_ratio  ? $width  : floor($size[0] * $ratio);
        $new_height  = !$use_x_ratio ? $height : floor($size[1] * $ratio);
        $new_left    = $use_x_ratio  ? 0 : floor(($width - $new_width)   / 2);
        $new_top     = !$use_x_ratio ? 0 : floor(($height - $new_height) / 2);
        $isrc  = $icfunc($src);
        $idest = imagecreatetruecolor($width, $height);
        imagefill($idest, 0, 0, $rgb);
        imagecopyresampled($idest, $isrc, $new_left, $new_top, 0, 0, $new_width, $new_height, $size[0], $size[1]);

        imagejpeg($idest, $dest, $quality);

        imagedestroy($isrc);
        imagedestroy($idest);

        return true;
    }

    /**
     * Сгенерировать пароль
     * @param int $length
     * @return string
     */
    public static function genPassword($length = 10) {
        $chars="qazxswedcvfrtgbnhyujmkiolp1234567890QAZXSWEDCVFRTGBNHYUJMKIOLP";
        $length = intval($length);
        $size=strlen($chars)-1;
        $password = "";
        while($length--) $password.=$chars[rand(0,$size)];
        return $password;
    }

    /**
     * Получить склонение ед. измерения
     * @param $number
     * @param $variants
     * @return string
     */
    public static function declension($number, $variants){
        $variants = array_filter(explode('|', $variants));
        $i = preg_replace('/[^0-9]+/s','', $number) % 100;
        if($i >= 5 && $i <= 20) {
            $res = $variants[2];
        }
        else {
            $i %= 10;
            if($i == 1) {
                $res = $variants[0];
            }
            else if($i >= 2 && $i <= 4) {
                $res = $variants[1];
            }
            else {
                $res = $variants[2];
            }
        }
        return trim($res);
    }
}