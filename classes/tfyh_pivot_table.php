<?php

/**
 * class file for the Tfyh_pivot_table class, providing pivot tables for Lists (Tfyh_list class). 
 * Currently only one row field, one column field and one data field with aggregation = sum.
 */
class Tfyh_pivot_table
{

    /**
     * The pivot table. two dimensional associative array of double.
     */
    private $ptable;

    /**
     * the column number of the row field in the list
     */
    private $rc;

    /**
     * the column number of the column field in the list
     */
    private $cc;

    /**
     * the column number of the data field in the list
     */
    private $dc;

    /**
     * the row itens of the pivot table
     */
    private $rItems;

    /**
     * the column itens of the pivot table
     */
    private $cItems;

    /**
     * the data aggregation method. See constructor for options
     */
    private $aggregation;

    /**
     * The contructor. Reas the list and builds the table upon construction.
     *
     * @param Tfyh_list $list
     *            the list to be pivoted
     * @param String $rowfield
     *            the row items field
     * @param String $columnfield
     *            the column items field
     * @param String $datafield
     *            the data field (must be numeric)
     * @param String $datafield
     *            the data aggregation method ("sum" or "cnt")
     */
    public function __construct (Tfyh_list $list, String $rowfield, String $columnfield, 
            String $datafield, String $aggregation)
    {
        $i = 0;
        $this->aggregation = $aggregation;
        $this->ptable = [];
        $this->rc = 0;
        $this->cc = 0;
        $this->dc = 0;
        $this->cItems = [];
        // extract the column indices of row, column and data field in the list.
        foreach (explode(",", $list->get_list_definition()["select"]) as $field_name) {
            if (strcasecmp($field_name, $rowfield) == 0)
                $this->rc = $i;
                if (strcasecmp($field_name, $columnfield) == 0)
                $this->cc = $i;
                if (strcasecmp($field_name, $datafield) == 0)
                $this->dc = $i;
            $i ++;
        }
        // extract the row field pivot items. Create a row within the pivot table for each.
        $this->rItems = $this->extract_pivot_item($list, $this->rc);
        // extract the column field pivot items. Create a row within the pivot table for each.
        $this->cItems = $this->extract_pivot_item($list, $this->cc);
        // initialize the pivot table
        $this->ptable = [];
        foreach ($this->rItems as $rItem) {
            $this->ptable[$rItem] = [];
            foreach ($this->cItems as $cItem) {
                $this->ptable[$rItem][$cItem] = 0;
            }
        }
        // sum up or count the data field.
        $dam = (strcasecmp($aggregation, "sum") == 0) ? 1 : 0;
        foreach ($list->get_rows() as $row) {
            $this->aggregate_data($row, $this->rc, $this->cc, $this->dc, $dam);
        }
    }

    /**
     * Aggregate a pivot data value.
     * 
     * @param array $row
     *            list row with data
     * @param int $rc
     *            index of row field column
     * @param int $cc
     *            index of column field column
     * @param int $dc
     *            index of data field column
     * @param int $dam
     *            data aggreagtion method: 0:count, 1:sum
     */
    private function aggregate_data (array $row, int $rc, int $cc, int $dc, int $dam)
    {
        $rItem = $row[$rc];
        if (strlen($rItem) == 0)
            $rItem = "(leer)";
        $cItem = $row[$cc];
        if (strlen($cItem) == 0)
            $cItem = "(leer)";
        $value = $row[$dc];
        if (strlen($value) > 0) {
            if ($dam == 0) // count
                $this->ptable[$rItem][$cItem] ++;
            elseif ($dam == 1) // sum
                $this->ptable[$rItem][$cItem] += doubleval($value);
        }
    }

    /**
     * Extract the pivot set of a column of the list
     *
     * @param Tfyh_list $list
     *            list which shall be pivoted
     * @param int $pc
     *            index of column in list which shall be pivoted
     * @return string[]|unknown[] array of pivot items
     */
    private function extract_pivot_item (Tfyh_list $list, int $pc)
    {
        $pitems = [];
        // extract the colum field pivot items. Create a row within the pivot table for each.
        foreach ($list->get_rows() as $row) {
            $entry = $row[$pc];
            if (strlen($entry) == 0)
                $entry = "(leer)";
            $found = false;
            foreach ($pitems as $pitem)
                if (strcasecmp($pitem, $entry) == 0)
                    $found = true;
            if (! $found)
                $pitems[] = $entry;
        }
        return $pitems;
    }

    /**
     * Get the pivot table as html string for web display.
     *
     * @param String $format
     *            number format for data, see native sprintf() for format String definitions.
     *            Default is "%d".
     * @return string pivot table as html String.
     */
    public function get_html (String $format = "%d")
    {
        // print header
        $html = "<table style='border: 2px solid'><tr><td style='padding-right: 5px;border: 1px solid;'>&nbsp;</td>"; // top left corner is empty
        foreach ($this->cItems as $cItem) {
            $html .= "<td style='padding-right: 5px;border: 1px solid;'>" . $cItem . "</td>";
        }
        $html .= "</tr>\n";
        // print rows
        foreach ($this->ptable as $rItem => $prow) {
            $html .= "<tr><td style='padding-right: 5px;'>" . $rItem . "</td>";
            foreach ($prow as $value)
                $html .= "<td style='padding-right: 5px;text-align:center'>" . sprintf($format, $value) . "</td>";
            $html .= "</tr>\n";
        }
        return $html . "</table>";
    }
}
    