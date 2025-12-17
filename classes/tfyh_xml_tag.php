<?php
/**
 *
 *       the tools-for-your-hobby framework
 *       ----------------------------------
 *       https://www.tfyh.org
 *
 * Copyright  2018-2024  Martin Glade
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License. 
 */


/**
 * class file for simple XML tag, supporting the Tfyh_xml class.
 */
class Tfyh_xml_tag
{

    /**
     * tag id
     */
    public $id = "";

    /**
     * attributes. Just a String, no parsing
     */
    public $attr = "";

    /**
     * text after open tag
     */
    public $txt_o = "";

    /**
     * text after close tag
     */
    public $txt_c = "";

    /**
     * true, if this is a close tag. Colse tags will be dropped after parsing.
     */
    public $is_close = true;

    /**
     * the parent tag
     */
    public $parent = null;

    /**
     * the parent tag
     */
    public $children = [];

    /**
     * public empty Constructor.
     */
    public function __construct ()
    {}

    /**
     * filter all children with tag id being $child_tag_id. Non-case sensitive comparison.
     * 
     * @param String $child_tag_id
     *            the tag id to filter
     * @return Tfyh_xml_tag[] the array of tags found. To empty array, if no chilren were found.
     */
    public function get_children (String $child_tag_id)
    {
        $filtered_children = [];
        foreach ($this->children as $child)
            if (strcasecmp($child->id, $child_tag_id) == 0)
                $filtered_children[] = $child;
        return $filtered_children;
    }

    /**
     * Get the first available child with tag id being $child_tag_id. Non-case sensitive comparison.
     * 
     * @param String $child_tag_id
     *            the tag id to filter
     * @return mixed|Tfyh_xml_tag the first child tag found. false, if no chilren were found.
     */
    public function get_first_child (String $child_tag_id)
    {
        $filtered_children = [];
        foreach ($this->children as $child) {
            // echo "Tag: " . $this->id . ", child: " . $child->id . ", looking for: " .
            // $child_tag_id . "<br>";
            if (strcasecmp($child->id, $child_tag_id) == 0)
                return $child;
        }
        return false;
    }
}
?>
