<?php

/**
 * This class provides a gallery utility. <p>The gallery can upload images, manipulate them and show a
 * thumbnail set of all files included.</p>
 */
class Tfyh_gallery
{

    /**
     * Definition of all galleries in a configuration file. Will be read once upon construction from
     * $file_path.
     */
    private $gallery_definitions;

    /**
     * Root path to gallery.
     */
    private $gallery_root;

    /**
     * list of all files in the gallery.
     */
    private $gallery_files;

    /**
     * list of all thumbnail files in the gallery.
     */
    private $thumb_files;

    /**
     * list of all preview files in the gallery.
     */
    private $preview_files;

    /**
     * key of list within list definitions. Will be read once upon construction from $file_path.
     */
    private $key;

    /**
     * Definition of gallery. Will be read once upon construction from $file_path.
     */
    private $gallery_definition;

    /**
     * The common toolbox used.
     */
    private $toolbox;

    /**
     * Build a gallery based on the definition provided in the csv file at $file_path.
     * 
     * @param String $file_path
     *            path to file with list definitions. List definitions contain of:
     *            key;title;validFrom;validTo;members. members is a comma (,) separated list of
     *            "Mitgliedsnummern".
     * @param int $key
     *            the key of the gallery to use. Set to "" to use the very first available.
     * @param Tfyh_toolbox $toolbox
     *            the application basic utilities
     */
    public function __construct (String $file_path, String $key, Tfyh_toolbox $toolbox)
    {
        $this->toolbox = $toolbox;
        $this->gallery_root = "../uploads/";
        $this->gallery_definition = [];
        $this->key = $key;
        $this->gallery_definitions = $toolbox->read_csv_array($file_path);
        // if definitions could be found, parse all and get own.
        if ($this->gallery_definitions !== false) {
            foreach ($this->gallery_definitions as $gallery_definition) {
                if (strlen($this->key) == 0)
                    $this->key = $gallery_definition["key"];
                if (strcasecmp($gallery_definition["key"], $this->key) === 0) {
                    $this->gallery_definition = $gallery_definition;
                    $this->load_gallery();
                }
            }
        } else
            $this->gallery_definition = false;
    }

    /**
     * Resize an image and store it under new filename. Use stored resized image, if existing. See
     * https://stackoverflow.com/questions/14649645/resize-image-in-php
     * 
     * @param String $image_file
     *            path to image file.
     * @param String $resized_image_file
     *            path to store resized image file.
     * @param int $w_new
     *            resized image width. If !$crop, the aspect ratio will be kept. Portrait format images will
     *            get $h_new height, landscape images get $w_new width.
     * @param int $h_new
     *            resized image height. If !$crop, the aspect ratio will be kept. Portrait format images will
     *            get $h_new height, landscape images get $w_new width.
     * @param bool $crop
     *            set true (default) to crop image rather than rescale.
     * @return resource resized image. Will return the previously resized image resource, if
     *         $resized_image_file does exist.
     */
    public function resize_image (String $image_file, String $resized_image_file, int $w_new, int $h_new, 
            bool $crop = true)
    {
        if (file_exists($resized_image_file)) {
            $dst = imagecreatefromjpeg($resized_image_file);
            return $dst;
        }
        list ($width, $height) = getimagesize($image_file);
        $aspect_ratio = $width / $height;
        $aspect_ratio_new = $w_new / $h_new;
        $delta_aspect_ratio = abs($aspect_ratio - $aspect_ratio_new);
        $src_x = 0;
        $src_y = 0;
        if ($crop) { // cropping will change the aspect ratio
            if ($width > $height) {
                $width = ceil($width - ($width * $delta_aspect_ratio));
                $src_x = ceil($width * $delta_aspect_ratio / 2);
            } else {
                $height = ceil($height - ($height * $delta_aspect_ratio));
                $src_y = ceil($height * $delta_aspect_ratio / 2);
            }
            $newwidth = $w_new;
            $newheight = $h_new;
        } else {
            if ($aspect_ratio < 1) {
                $newheight = $h_new;
                $newwidth = $h_new * $aspect_ratio;
            } else {
                $newwidth = $w_new;
                $newheight = $w_new / $aspect_ratio;
            }
        }
        $src = imagecreatefromjpeg($image_file);
        $dst = imagecreatetruecolor($newwidth, $newheight);
        imagecopyresampled($dst, $src, 0, 0, $src_x, $src_y, $newwidth, $newheight, $width, $height);
        imagejpeg($dst, $resized_image_file);
        return $dst;
    }

    /**
     * Return an html string which can be used to show a preview image in a separate roaster. The html is a
     * complete htl page. The image is base64 encoded and included, together with links to previous and next
     * image.
     * 
     * @param String $preview_image_name
     *            the file name of the preview.
     */
    public function get_preview_html (String $preview_image_name)
    {
        $prev_preview_file = "";
        $this_preview_file = "";
        $next_preview_file = "";
        $done = false;
        foreach ($this->preview_files as $preview_file) {
            if (! $done) {
                $prev_preview_file = $this_preview_file;
                $this_preview_file = $next_preview_file;
                $next_preview_file = $preview_file;
                $done = (strcasecmp($preview_image_name, $this_preview_file) === 0);
            }
        }
        $html = '<!DOCTYPE html><html lang="de-DE"><head>' . '<meta charset="UTF-8"><title>BRG - ' .
                 $this->gallery_definition["title"] . " - " . $this_preview_file .
                 "</title><link rel='stylesheet' href='/css/style.css' type='text/css' media='screen'>" .
                 "</head><body style='text-align:center;background-color: black;margin:0px;padding:0px;'>";
        $html .= "<div id=wrapper style='text-align:center;background-color: black;margin:0px;padding:0px;'>" .
                 "<div style='display:inline-block;'>" . "<img src='data:image/gif;base64," . base64_encode(
                        file_get_contents("../uploads/" . $this->key . "/previews/" . $this_preview_file)) .
                 "'><br>" . "<span style='color:white;font-size:3em;line-height:1.5em;'><a href='?gallery=" .
                 $this->key . "&preview=" . $prev_preview_file .
                 "'>&#9664;</a>&nbsp;&nbsp;&nbsp;&nbsp;<a href='?gallery=" . $this->key . "&preview=" .
                 $next_preview_file . "'>&#9654;</a></span>" . "</div></div></body></html>";
        return $html;
    }

    /**
     * Return an html string which can be used to build a gallery. The name of the file is displayed together
     * with a checkbox having the very same name, so that it can be embedded in a form. The html is framed by
     * a &lt;td&gt; tag. The image is base64 encoded and included.
     * 
     * @param String $thumb_image_file
     *            the file path to the thumbnail.
     */
    private function get_thumb_html (String $thumb_image_file)
    {
        $thumb_image_name = basename($thumb_image_file);
        $html = "<td style='border: 0px; padding:5px; text-align:center;'><a href='?gallery=" . $this->key .
                 "&preview=" . $thumb_image_name . "' target='_blank'><img src='data:image/gif;base64," .
                 base64_encode(file_get_contents($thumb_image_file)) .
                 "'></a><br /><input type='checkbox' name='" . $thumb_image_name . "'>&nbsp;" .
                 $thumb_image_name . "</td>";
        return $html;
    }

    /**
     * load the entire gallery for later display. Actually only loads all file paths found.
     */
    private function load_gallery ()
    {
        $this->gallery_files = array();
        $dir = opendir($this->gallery_root . $this->gallery_definition["key"] . "/");
        if ($dir) {
            $file = readdir($dir);
            while ($file) {
                if ($file != '.' && $file != '..') {
                    $this->gallery_files[] = basename($file);
                }
                $file = readdir($dir);
            }
            closedir($dir);
        }
        natsort($this->gallery_files); // sort
        
        $this->preview_files = array();
        $dir = opendir($this->gallery_root . $this->gallery_definition["key"] . "/previews/");
        if ($dir) {
            $file = readdir($dir);
            while ($file) {
                if ($file != '.' && $file != '..') {
                    $this->preview_files[] = basename($file);
                }
                $file = readdir($dir);
            }
            closedir($dir);
        }
        natsort($this->preview_files); // sort
        
        $this->thumb_files = array();
        $dir = opendir($this->gallery_root . $this->gallery_definition["key"] . "/thumbs/");
        if ($dir) {
            $file = readdir($dir);
            while ($file) {
                if ($file != '.' && $file != '..') {
                    $this->thumb_files[] = basename($file);
                }
                $file = readdir($dir);
            }
            closedir($dir);
        }
        natsort($this->thumb_files); // sort
    }

    /**
     * Simple getter
     * 
     * @return array all gallery definitions retrieved from the list definition file to which the user has
     *         access. Will be an empty array on errors.
     */
    public function get_all_definitions (int $mitgliedsnummer)
    {
        $gallery_definitions = array();
        foreach ($this->gallery_definitions as $gallery_definition) {
            if ($this->is_allowed($mitgliedsnummer, $gallery_definition))
                $gallery_definitions[] = $gallery_definition;
        }
        return $gallery_definitions;
    }

    /**
     * Check whether the user shall be granted access to the gallery.
     * 
     * @return boolean true, if access is allowed, false, if not
     */
    public function is_allowed (int $mitgliedsnummer, array $gallery_definition = null)
    {
        if (is_null($gallery_definition))
            $gallery_definition = $this->gallery_definition;
        $user_token = "-" . $mitgliedsnummer . "-";
        $is_allowed_user = (strpos($gallery_definition["members"], $user_token) !== false);
        return $is_allowed_user;
    }

    /**
     * Return a html code of this gallery based on its definition or the provided options. Includes a form for
     * upload and download.
     * 
     * @param int $n_columns
     *            the count of columns within the gallery
     * @return string html formatted table for web display.
     */
    public function get_html (int $mitgliedsnummer, int $n_columns)
    {
        if (count($this->gallery_definition) === 0)
            return "<p>" . i("tg2LqL|Application configuratio...", $this->gallery_definition["title"]) . "</p>";
        
        $gallery_html = '<p><b>' . i("KLgQSh|Upload:") . '</b><br />' . i(
                'foQQVH| ** Above the pictures c...') . "</p>";
        
        $gallery_html .= '<form action="?gallery=' . $this->gallery_definition["key"] .
                 '&upload=1" method="post" enctype="multipart/form-data"><p>' .
                 i('saSTkk|Select images to upload:') . " " .
                 '<input type="file" name="fileToUpload[]" multiple="multiple">' .
                 '<input type="submit" value="' . i('txXT1g|Upload') . '" name="upload"></form><br />' . '<b>' . i(
                        '8MYKLF|After clicking on the °U...') . '</b>';
        
        // build table header
        $gallery_html .= '<p><b>ALBUM ' . $this->gallery_definition["title"] . "</b>";
        $gallery_html .= '&nbsp;&nbsp;&nbsp;<a href="?gallery=' . $this->gallery_definition["key"] . '">' .
                 i('iCqOma|update after upload') . '</a>';
        $gallery_html .= (count($this->thumb_files) == 0) ? '<br />' . i('vI6UBA|No pictures available in...') : '';
        $gallery_html .= '<form action="?gallery=' . $this->gallery_definition["key"] .
                 '&download=1"  method="post"> ';
        $gallery_html .= '</p><div style="overflow-x: auto; white-space: nowrap;"><table width=800px style="border: 0px;"><tr>';
        $i_columns = 0;
        $thumb_dir = $this->gallery_root . $this->gallery_definition["key"] . "/thumbs/";
        foreach ($this->thumb_files as $thumb_file) {
            if ($i_columns == $n_columns) {
                $i_columns = 0;
                $gallery_html .= "</tr><tr>\n";
            }
            $gallery_html .= $this->get_thumb_html($thumb_dir . $thumb_file);
            $i_columns ++;
        }
        $gallery_html .= '</tr></table></div><input type="submit" value="' . i('YEcCUE|Download') .
                 '" name="download"></form>';
        $gallery_html .= '<p><b>' . i('2FZhhA|Download:') . '</b><br />' . i(
                'XBFzrv|Check the box for pictur...') . '</p>';
        
        return $gallery_html;
    }

    /**
     * Uploads all images posted as "fileToUpload", i. e. when using the get_html() function to create the
     * form display.
     * 
     * @param String $user_name
     *            name of user for file naming, e.g. John_Doe
     * @return string upload result String for display.
     */
    public function upload_images (String $user_name)
    {
        $target_dir = "../uploads/" . $this->key . "/";
        $thumbnail_dir = "../uploads/" . $this->key . "/thumbs/";
        $preview_dir = "../uploads/" . $this->key . "/previews/";
        if (! is_dir($target_dir))
            mkdir($target_dir);
        if (! is_dir($thumbnail_dir))
            mkdir($thumbnail_dir);
        if (! is_dir($preview_dir))
            mkdir($preview_dir);
        
        $upload_res = "";
        // Loop through each file
        $total = count($_FILES["fileToUpload"]["tmp_name"]);
        for ($i = 0; $i < $total; $i ++) {
            // Check if image file is a actual image or fake image
            $check = getimagesize($_FILES["fileToUpload"]["tmp_name"][$i]);
            if ($check !== false) {
                $upload_res .= i("4kkzns|Image was recognized.") . " ";
                $exif = exif_read_data($_FILES["fileToUpload"]["tmp_name"][$i]);
                if ($exif === false) {
                    $upload_res .= i("I3zknU|No meta data found.") . " ";
                    $captured = date("Ymd_His");
                } else {
                    $upload_res .= i("jdB8fj|The uploaded image #%1 i...", strval($i + 1)) . " " .
                             $exif["DateTimeOriginal"];
                    if (isset($exif["DateTimeOriginal"])) {
                        $date_time_original_exif = DateTime::createFromFormat("Y:m:d H:i:s", 
                                $exif["DateTimeOriginal"]);
                        $timestamp_original = strtotime($date_time_original_exif->format("Y-m-d H:i:s"));
                        $captured = date("Ymd_His", $timestamp_original);
                    } else {
                        $captured = date("Ymd_His");
                    }
                    $upload_res .= ", " . i("eosDhG|Size (kB):") . " " . intval($exif["FileSize"] / 1024);
                    $upload_res .= ", " . i("v9Y9Xk|Format:") . " " . $exif["COMPUTED"]["Width"] . "*" .
                             $exif["COMPUTED"]["Height"] . ".";
                }
                $target_file = $target_dir . $captured . $user_name . ".jpg";
                $thumbnail_file = $thumbnail_dir . $captured . $user_name . ".jpg";
                $preview_file = $preview_dir . $captured . $user_name . ".jpg";
                if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"][$i], $target_file)) {
                    $upload_res .= " " . i("ZjU51G|The File °%1° was upload...", 
                            basename($_FILES["fileToUpload"]["name"][$i])) . "<br />";
                    $thumbnail = $this->resize_image($target_file, $thumbnail_file, 150, 150);
                    $preview = $this->resize_image($target_file, $preview_file, 1200, 1200, false);
                    if (! empty($exif['Orientation'])) {
                        switch ($exif['Orientation']) {
                            case 3:
                                $thumbnail = imagerotate($thumbnail, 180, 0);
                                $preview = imagerotate($preview, 180, 0);
                                break;
                            
                            case 6:
                                $thumbnail = imagerotate($thumbnail, - 90, 0);
                                $preview = imagerotate($preview, - 90, 0);
                                break;
                            
                            case 8:
                                $thumbnail = imagerotate($thumbnail, - 90, 0);
                                $preview = imagerotate($preview, - 90, 0);
                                break;
                        }
                    }
                } else {
                    $upload_res .= i("fUMqe5|The File could not be up...", 
                            $_FILES["fileToUpload"]["error"][$i]) . "<br />";
                }
            } else {
                $upload_res .= i("KyVvaE|No image was detected in...", strval($i + 1), 
                        $_FILES["fileToUpload"]["tmp_name"][$i]) . " <br />";
            }
        }
        return $upload_res;
    }

    /**
     * Downloads selected images when using the get_html() function to create the form display. Function exits
     * on normal download without any display.
     * 
     * @return string download result String for display.
     */
    public function download_images ()
    {
        $target_dir = "../uploads/" . $this->key . "/";
        if (! is_dir($target_dir))
            return i("d3o4EA|Album not found. Cancel.");
        
        $download_res = "";
        $n_downloads = 0;
        $zip = new ZipArchive();
        $zipname = $target_dir . 'download.zip';
        $zip_open_res = $zip->open($zipname, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($zip_open_res === TRUE) {
            foreach ($_POST as $key => $value) {
                if ((strcasecmp("on", $value) == 0) && ($n_downloads < 10)) {
                    $n_downloads ++;
                    $key_fixed = str_replace("_jpg", ".jpg", $key);
                    $zip->addFile($target_dir . $key_fixed, $key_fixed);
                }
            }
        }
        $zip->close();
        
        // return zip.
        if (file_exists($zipname)) {
            header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
            header("Cache-Control: public"); // needed for internet explorer
            header("Content-Type: application/zip");
            header("Content-Transfer-Encoding: Binary");
            header("Content-Length:" . filesize($zipname));
            header("Content-Disposition: attachment; filename=" . $zipname);
            readfile($zipname);
            unlink($zipname);
            exit();
        } else {
            return i("SPDW0F|The zip file °%1° could ...", $zipname);
        }
    }
}
