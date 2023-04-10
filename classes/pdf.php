<?php

/**
 * A class to produce a pdf based on a html layout and a set of data from the data base.
 */
class PDF
{

    /**
     * The data base socket to retrieve the data which shall replace the field-identifiers in the template.
     */
    private $socket;

    /**
     * The framework ttolbox
     */
    private $toolbox;

    /**
     * The name of the table to be used for field identifiers (= column names)
     */
    private $table_name;

    /**
     * Constructor just takes the arguments and links it to local fields.
     * 
     * @param Tfyh_socket $socket
     *            the data base socket to retrieve the data which shall replace the field-identifiers in the
     *            template.
     * @param String $table_name
     *            the name of the table to be used for field identifiers (= column names)
     */
    public function __construct (Tfyh_toolbox $toolbox, Tfyh_socket $socket, String $table_name)
    {
        $this->table_name = $table_name;
        $this->toolbox = $toolbox;
        $this->socket = $socket;
    }

    /**
     * Create a pdf based on the table data
     * 
     * @param String $template_name
     *            name of template to be used. Will be extended by ".html" for template load and by "_$id.pdf"
     *            for created file. Will be used as document title for PDF file.
     * @param String $subject
     *            subject of pdf document
     * @param int $id
     *            the id of the data set to be used. Query run on " WHERE `ID` = $id".
     * @param array $direct_values
     *            (optional) an named array of values which may have been deduced by a separate logic.
     */
    function create_pdf (String $template_name, String $subject, int $id, array $direct_values = array())
    {
        $template_path = "../templates/" . $template_name . ".html";
        $html = $this->fill_html_template($template_path, $id, $direct_values);
        // vvv for debugging purposes, if the PDF is empty.
        // file_put_contents("../pdfs/" . $template_name . "_" . $id . ".html", $html);
        // ^^^ for debugging purposes, if the PDF is empty.
        $pdf_path = "../pdfs/" . $template_name . "_" . $id . ".pdf";
        $this->convert_to_pdf($html, $pdf_path, $template_name, $subject);
        chmod($pdf_path, 0766);
        // vvv for debugging purposes, if the PDF is empty.
        // copy($pdf_path, $pdf_path . ".tmp");
        // ^^^ for debugging purposes, if the PDF is empty.
        return $pdf_path;
    }

    /**
     * Remove all created pdf files. To be used by Tfyh_cron_jobs.
     */
    public static function clear_all_created_files ()
    {
        $files = scandir("../pdfs");
        if ($files !== false)
            foreach ($files as $file)
                if (strcmp(substr($file, 0, 1), ".") != 0)
                    unlink("../pdfs/$file");
    }

    /**
     * Create a pdf document from the provided html String. Margins, footer text and document author are taken
     * from the app configuration.
     * 
     * @param String $html
     *            string to convert
     * @param String $file_path
     *            relative file path to save the result to
     * @param String $title
     *            title of pdf document
     * @param String $subject
     *            subject of pdf document
     */
    function convert_to_pdf (String $html, String $file_path, String $title, String $subject)
    {
        // TCPDF Library laden
        require_once ('../tcpdf/tcpdf.php');
        require_once ('../classes/pdf_adapted.php');
        
        // Erstellung des PDF Dokuments
        $pdf = new PDF_adapted(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->footer_text = $this->toolbox->config->pdf_footer_text;
        
        // Dokumenteninformationen
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor($this->toolbox->config->pdf_document_author);
        $pdf->SetTitle($title);
        $pdf->SetSubject($subject);
        
        // Header und Footer Informationen
        $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN,'',PDF_FONT_SIZE_MAIN
        ));
        $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA,'',PDF_FONT_SIZE_DATA
        ));
        
        // Auswahl der Margins
        $pdf->SetMargins($this->toolbox->config->pdf_margins[0], $this->toolbox->config->pdf_margins[1], 
                $this->toolbox->config->pdf_margins[2], true);
        $pdf->SetHeaderMargin($this->toolbox->config->pdf_margins[3]);
        $pdf->SetFooterMargin($this->toolbox->config->pdf_margins[4]);
        
        // remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        
        // Automatisches Autobreak der Seiten
        $pdf->SetAutoPageBreak(TRUE, 15);
        
        // Image Scale
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        
        // Schriftart
        $pdf->SetFont('dejavusans', '', 9);
        
        // Neue Seite
        $pdf->AddPage();
        
        // Fügt den HTML Code in das PDF Dokument ein
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // PDF im Directory abspeichern:
        $saved_at = dirname(__FILE__) . '/' . $file_path;
        $pdf->Output($saved_at, 'F');
    }

    /**
     * Fill a template with the values from a single data set of the data base. Referenced values are looked
     * up.
     * 
     * @param String $template_path
     *            the file path for the template, e.g. "../templates/templ1.html"
     * @param int $id
     *            the id of the data set to be used. Queries the Mitgliederliste on " WHERE `ID` = $id".
     * @param array $direct_values
     *            an named array of values which may have been deduced by a separate logic.
     */
    private function fill_html_template (String $template_path, int $id, array $direct_values)
    {
        // read template and fill in
        $template_string = file_get_contents($template_path);
        $filled_string = "";
        $snippet_start = 0;
        $snippet_end = strpos($template_string, "{#");
        $data_set = null;
        while ($snippet_end !== false) {
            // copy snippet and get find-value
            $filled_string .= substr($template_string, $snippet_start, $snippet_end - $snippet_start);
            $token_start = $snippet_end + 2;
            $token_end = strpos($template_string, "#}", $token_start);
            $find_string = substr($template_string, $token_start, $token_end - $token_start);
            $find_elements = explode(".", $find_string);
            $snippet_start = $token_end + 2;
            $snippet_end = strpos($template_string, "{#", $snippet_start);
            if (! is_null($direct_values) && (count($find_elements) == 1)) {
                // use provided values of $direct_values array
                $value_to_use = $direct_values[$find_elements[0]];
            } else {
                // find direct data set
                if (! isset($data_set[$find_elements[0]]))
                    $data_set[$find_elements[0]] = $this->socket->get_record($find_elements[0], $id);
                // look up secondary data set, if needed
                if (count($find_elements) == 3) {
                    $secondary_table_path = $find_elements[0] . "." . $find_elements[1];
                    if (! isset($data_set[$secondary_table_path])) {
                        $secondary_id = $data_set[$find_elements[0]][$find_elements[1]];
                        $data_set[$secondary_table_path] = $this->socket->get_record($find_elements[1], 
                                $secondary_id);
                    }
                    $value_to_use = $data_set[$secondary_table_path][$find_elements[2]];
                } else {
                    $value_to_use = $data_set[$find_elements[0]][$find_elements[1]];
                }
            }
            // add value to filled string and continue
            $filled_string .= (strlen($value_to_use) == 0) ? "-" : $value_to_use;
        }
        // add the remainder of the template to the filled template.
        $filled_string .= substr($template_string, $snippet_start, strlen($template_string) - $snippet_start);
        return $filled_string;
    }
}
