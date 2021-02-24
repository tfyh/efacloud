<?php

/**
 * class file to change names and Id to anonymize a logbook for demo purposes.
 *
 * @package efacloud
 * @subpackage classes
 * @author mgSoft
 */
class Anonymizer
{

    /**
     * list of 101 German first names
     *
     * @var array
     */
    private $first_names = [
            'Adalbert',
            'Adrian',
            'Agnes',
            'Albert',
            'Alexander',
            'Alfons',
            'Alfred',
            'Alois',
            'Andreas',
            'Anna',
            'Annemarie',
            'Anton',
            'Antonia',
            'August',
            'Babette',
            'Balthasar',
            'Barbara',
            'Benedikt',
            'Benno',
            'Bernadette',
            'Bernhard',
            'Berthold',
            'Bettina',
            'Birgit',
            'Brigitte',
            'Cäcilia',
            'Caroline',
            'Christian',
            'Christine',
            'Christoph',
            'Dorothea',
            'Engelbert',
            'Eva',
            'Ferdinand',
            'Florian',
            'Franz',
            'Franziska',
            'Friedrich',
            'Georg',
            'Gertrud',
            'Gregor',
            'Hannelore',
            'Herbert',
            'Hubert',
            'Jakob',
            'Joachim',
            'Johanna',
            'Johannes',
            'Josef',
            'Josefine',
            'Joseph',
            'Karl',
            'Kaspar',
            'Katharina',
            'Katrin',
            'Konrad',
            'Korbinian',
            'Leonhard',
            'Leopold',
            'Lorenz',
            'Ludwig',
            'Lukas',
            'Magdalena',
            'Manfred',
            'Margaret',
            'Maria',
            'Marie',
            'Markus',
            'Marlene',
            'Martin',
            'Martina',
            'Mathilde',
            'Matthias',
            'Maximilian',
            'Michael',
            'Moritz',
            'Peter',
            'Philipp',
            'Reinhard',
            'Reinhold',
            'Robert',
            'Rudolf',
            'Sabine',
            'Sebastian',
            'Siegfried',
            'Sieglinde',
            'Simon',
            'Sophie',
            'Stefan',
            'Stefanie',
            'Susanne',
            'Theresia',
            'Thomas',
            'Ursula',
            'Valentin',
            'Verena',
            'Veronika',
            'Victoria',
            'Vinzenz',
            'Wolfgang',
            'Zacharias'
    ];

    /**
     * list of 100 German last names
     *
     * @var array
     */
    private $last_names = [
            'Müller',
            'Schmidt',
            'Schneider',
            'Fischer',
            'Weber',
            'Meyer',
            'Wagner',
            'Becker',
            'Schulz',
            'Hoffmann',
            'Schäfer',
            'Koch',
            'Bauer',
            'Richter',
            'Klein',
            'Wolf',
            'Schröder',
            'Neumann',
            'Schwarz',
            'Zimmermann',
            'Braun',
            'Krüger',
            'Hofmann',
            'Hartmann',
            'Lange',
            'Schmitt',
            'Werner',
            'Schmitz',
            'Krause',
            'Meier',
            'Lehmann',
            'Schmid',
            'Schulze',
            'Maier',
            'Köhler',
            'Herrmann',
            'König',
            'Walter',
            'Mayer',
            'Huber',
            'Kaiser',
            'Fuchs',
            'Peters',
            'Lang',
            'Scholz',
            'Möller',
            'Weiß',
            'Jung',
            'Hahn',
            'Schubert',
            'Vogel',
            'Friedrich',
            'Keller',
            'Günther',
            'Frank',
            'Berger',
            'Winkler',
            'Roth',
            'Beck',
            'Lorenz',
            'Baumann',
            'Franke',
            'Albrecht',
            'Schuster',
            'Simon',
            'Ludwig',
            'Böhm',
            'Winter',
            'Kraus',
            'Martin',
            'Schumacher',
            'Krämer',
            'Vogt',
            'Stein',
            'Jäger',
            'Otto',
            'Sommer',
            'Groß',
            'Seidel',
            'Heinrich',
            'Brandt',
            'Haas',
            'Schreiber',
            'Graf',
            'Schulte',
            'Dietrich',
            'Ziegler',
            'Kuhn',
            'Kühn',
            'Pohl',
            'Engel',
            'Horn',
            'Busch',
            'Bergmann',
            'Thomas',
            'Voigt',
            'Sauer',
            'Arnold',
            'Wolff',
            'Pfeiffer'
    ];

    private $crew_names = [
            "CoxName",
            "Crew1Name",
            "Crew2Name",
            "Crew3Name",
            "Crew4Name",
            "Crew5Name",
            "Crew6Name",
            "Crew7Name",
            "Crew8Name"
    ];

    private $boat_names = [
            'Weser',
            'Werra',
            'Weiße Elster',
            'Spree',
            'Salzach',
            'Saar',
            'Saale',
            'Ruhr',
            'Rhein',
            'Oder',
            'Neckar',
            'Mulde',
            'Mosel',
            'Main',
            'Lippe',
            'Leine',
            'Lech',
            'Lahn',
            'Kocher',
            'Isar',
            'Inn',
            'Havel',
            'Fulda',
            'Ems',
            'Elde',
            'Elbe',
            'Eger',
            'Donau',
            'Altmühl',
            'Aller',
            'Loire',
            'Maas',
            'Rhône',
            'Seine',
            'Garonne',
            'Marne',
            'Lot',
            'Dordogne',
            'Saône',
            'Doubs',
            'Allier',
            'Charente',
            'Tarn',
            'Cher',
            'Vienne',
            'Schelde',
            'Aisne',
            'Oise',
            'Durance',
            'Loir',
            'Sarthe',
            'Adour',
            'Yonne',
            'Aveyron',
            'Isère',
            'Indre',
            'Creuse',
            'Isle',
            'Aube',
            'Somme',
            'Eure',
            'Aude',
            'Vilaine',
            'Ognon',
            'Vézère',
            'Ill',
            'Gartempe',
            'Armançon',
            'Mayenne',
            'Dronne',
            'Agout',
            'Sambre',
            'Ain',
            'Baïse',
            'Serein',
            'Sauldre',
            'Gave de Pau',
            'Gers',
            'Orne',
            'Viaur',
            'Truyère',
            'Verdon',
            'Ariège',
            'Sioule',
            'Arrats',
            'Meurthe',
            'Arnon',
            'Blavet',
            'Hérault',
            'Oust',
            'Risle',
            'Clain',
            'Aire',
            'Save',
            'Thouet',
            'Loing',
            'Aulne',
            'Dore',
            'Vesle',
            'Seille',
            'Gimone',
            'Hers-Vif',
            'Orb',
            'Dropt',
            'Iton',
            'Arros',
            'Drac',
            'Arroux',
            'Arc',
            'Cèze',
            'Vire',
            'Gardon',
            'Loue',
            'Ardèche',
            'Cère',
            'Osse',
            'Lay',
            'Chiers',
            'Cure',
            'Drôme',
            'Authie',
            'Taurion',
            'Touques',
            'Besbre',
            'Ornain',
            'Dives',
            'Aron',
            'Célé',
            'Oudon',
            'Arve',
            'Rance',
            'Couesnon',
            'Arconce',
            'Authion',
            'Gave d’Oloron',
            'Louge',
            'Ource',
            'Boutonne',
            'Ciron',
            'Erdre',
            'Essonne',
            'Madon',
            'Seiche',
            'Yerres',
            'Zorn'
    ];

    private $dummy_text = "Faszination Rudern" .
             "Rudern ist ein naturverbundener Wassersport. Er verbindet Kraft und Ausdauer, Teamgeist und Dynamik. Zu fast allen Jahreszeiten kann man Flüsse und Seen mit dem Boot erkunden." .
             "In Deutschland hat Rudern eine über 150-jährige Tradition. Heute ist Rudern nicht nur Wettkampfsport und Olympische Disziplin, sondern auch ein für jedermann geeigneter Breitensport. Ganz gleich, ob man dabei lieber allein oder im Team aktiv wird, vom Einer bis zum Achter findet sich für jeden Wunsch das passende Boot. Um regelmäßig zu rudern, sollte man zwar aus dem Grundschulalter heraus sein, nach oben hin gibt es aber bis ins hohe Alter keine Grenze. Da Rudern ein sehr geringes Verletzungsrisiko birgt und schonend alle Muskelgruppen beansprucht, eignet es sich für jeden Fitnessgrad. So engagieren sich immer mehr Vereine auch im Handicaprudern, das behinderten Menschen die Möglichkeit zur sportlichen Betätigung bietet." .
             "Rudern in der Freizeit" .
             "Rudern bedeutet, der Natur nah zu sein und körperlichen Ausgleich zu finden. Dazu gehören ausgedehnte Wanderausflüge und Tagesfahrten, aber auch Regatten und Fitnesstraining. Dabei ist es egal, ob man lieber allein, mit dem Partner oder in einer großen Gruppe ist, denn dank verschiedener Bootsklassen lässt sich jedes Rudererlebnis individuell gestalten. Dafür wird man am besten in einem der rund 500 Rudervereine Mitglied, die es nahezu überall in Wassernähe gibt. Mit ihren schön gelegenen Bootshäusern bilden  sie die ideale Basis für eine gesunde und vielfältige Freizeitgestaltung." .
             "Spaß und Freizeitvergnügen müssen aber nicht die einzigen Gründe sein, in einem Verein aktiv zu werden. Vielmehr bietet er die Möglichkeit zu ehrenamtlichen Tätigkeiten und damit die Chance, über das Berufsleben hinaus Verantwortung zu übernehmen und Bestätigung zu finden." .
             "Rudern für die Gesundheit" .
             "Rudern gehört zu den wenigen Sportarten, die nahezu alle Muskelgruppen beanspruchen und gleichzeitig Ausdauer, Koordination, Herz und Kreislauf trainieren. Da außerdem das Verletzungsrisiko sehr gering ist, hat Rudern einen hohen gesundheitlichen Wert. Aus diesem Grund eignet es sich besonders gut zur Rehabilitation sowie zur gesundheitlichen Prävention. Darüber hinaus unterstützt Rudersport den Abbau von Stress. Verbunden mit der Natur und der beruhigenden Wirkung des Wassers ist Rudern somit nicht nur eine Wohltat für den Körper – sondern auch für den Geist. Dies gilt natürlich auch für Menschen mit Behindeungen. Nahezu jede Art von Behinderung kann im Rudersport inkludiert werden. " .
             "Rudern auf der Regatta" .
             "Das ganze Jahr über finden zahlreiche deutsche und internationale Ruderregatten statt, die nicht nur für die Teilnehmer, sondern auch für die Zuschauer ein spannendes Erlebnis sind. Von Wettbewerben auf lokaler Ebene bis hin zu den Olympischen Spielen, ob Sprint oder Langstrecke bietet sich für jede Leistungsklasse und jeden Anspruch die passende Veranstaltung." .
             "Wer sich also gern mit anderen messen möchte und bereit ist, dafür regelmäßig zu trainieren, kann beim Rudern viel erreichen. Natürlich braucht es Disziplin, Ehrgeiz und Ausdauer, um mehrmals pro Woche oder sogar täglich ins Boot zu steigen. Nimmt man das aber auf sich, wird man nicht nur mit Muskelkraft und einer erhöhten Koordinationsfähigkeit, sondern auch mit mentaler Stärke belohnt. Durch regelmäßiges Training und sportliche Erfolge bestätigt, weichen Ruderer auch vor anderen Herausforderungen im Leben nicht zurück. Sie verstehen es, konzentriert ihre Ziele zu verfolgen, sind teamfähig und ausdauernd.";

    private $crew_ids = ["CoxId","Crew1Id","Crew2Id","Crew3Id","Crew4Id","Crew5Id","Crew6Id","Crew7Id","Crew8Id"
    ];

    private $firstname_index = 0;

    private $lastname_index = 0;

    private $socket;

    private $shift_cache;

    /**
     * public Constructor. Runs the anonymization.
     */
    public function __construct (Socket $socket, int $efaCloudUserID)
    {
        $this->socket = $socket;
        $this->dummy_text = $this->dummy_text;
        echo "<h3>Anonymizing tables.</h4><p>Anonymizing boat names.<br>";
        flush();
        ob_flush();
        $this->anonymize_boat_names($efaCloudUserID);
        echo "Anonymizing person names.<br>";
        flush();
        ob_flush();
        $this->anonymize_persons($efaCloudUserID);
        echo "Shifting crews.<br>";
        flush();
        ob_flush();
        $this->shift_crews($efaCloudUserID);
        echo "Replacing texts.<br>";
        flush();
        ob_flush();
        $this->replace_texts($efaCloudUserID);
        echo "Done.</p>";
    }

    /**
     * Iterate through names, first and last in parallel and wrapping. Because the count of first
     * namens is 101 and that of last names 100, 10.000 different names are generated before the
     * sequence restarts.
     */
    private function next_name ()
    {
        $next_name = $this->first_names[$this->firstname_index] . " " . $this->last_names[$this->lastname_index];
        $this->firstname_index ++;
        $this->lastname_index ++;
        if ($this->firstname_index >= count($this->first_names))
            $this->firstname_index = 0;
        if ($this->lastname_index >= count($this->last_names))
            $this->lastname_index = 0;
        return $next_name;
    }

    /**
     * Replace auxiliary texts.
     */
    private function replace_texts (int $efaCloudUserID)
    {
        $boatdamages = $this->socket->find_records("efa2boatdamages", "", "", 1000, true);
        $i = 0;
        foreach ($boatdamages as $boatdamage) {
            $boatdamage_new = [];
            $boatdamage_new["Damage"] = $boatdamage["Damage"];
            if ($boatdamage["Description"] && strlen($boatdamage["Description"]) > 0)
                $boatdamage_new["Description"] = substr($this->dummy_text, 0, strlen($boatdamage["Description"]));
            if ($boatdamage["LogbookText"] && strlen($boatdamage["LogbookText"]) > 0)
                $boatdamage_new["LogbookText"] = substr($this->dummy_text, 0, strlen($boatdamage["LogbookText"]));
            $res = $this->socket->update_record_matched($efaCloudUserID, "efa2boatdamages", "Damage", $boatdamage_new, 
                    true);
        }
        $boatreservations = $this->socket->find_records("efa2boatreservations", "", "", 1000, true);
        foreach ($boatreservations as $boatreservation) {
            $boatreservation_new = [];
            $boatreservation_new["Reservation"] = $boatreservation["Reservation"];
            if ($boatreservation["Contact"] && strlen($boatreservation["Contact"]) > 0)
                $boatreservation_new["Contact"] = "[Kontakt entfernt]";
            $this->socket->update_record_matched($efaCloudUserID, "efa2boatreservations", "Reservation", 
                    $boatreservation_new, true);
        }
        $boatstatuses = $this->socket->find_records("efa2boatstatus", "", "", 1000, true);
        foreach ($boatstatuses as $boatstatus) {
            $boatstatus_new = [];
            $boatstatus_new["BoatId"] = $boatstatus["BoatId"];
            if ($boatstatus["BoatText"] && strlen($boatstatus["BoatText"]) > 0)
                $boatstatus_new["BoatText"] = "[Name des Bootes entfernt]";
            $this->socket->update_record_matched($efaCloudUserID, "efa2boatstatus", "BoatId", $boatstatus_new, true);
        }
        // efa2crews has no texts besides the crew name
        // efa2destinations has no person related texts
        $fahrtenhefte = $this->socket->find_records("efa2fahrtenabzeichen", "", "", 1000, true);
        foreach ($fahrtenhefte as $fahrtenheft) {
            $fahrtenheft_new = [];
            $fahrtenheft_new["PersonId"] = $fahrtenheft["PersonId"];
            if ($fahrtenheft["Fahrtenheft"] && strlen($fahrtenheft["Fahrtenheft"]) > 0)
                $fahrtenheft_new["Fahrtenheft"] = "[Inhalt des Fahrtenheftes entfernt]";
            $this->socket->update_record_matched($efaCloudUserID, "efa2fahrtenabzeichen", "PersonId", $fahrtenheft_new, 
                    true);
        }
        // efa2groups has no texts besides the crew name
        $messages = $this->socket->find_records_matched("efa2messages", "", "", 1000, true);
        foreach ($messages as $message) {
            $message_new = [];
            $message_new["MessageId"] = $message["MessageId"];
            if ($message["From"] && strlen($message["From"]) > 0)
                $message_new["From"] = "[Autor entfernt]";
            if ($message["ReplyTo"] && strlen($message["ReplyTo"]) > 0)
                $message_new["ReplyTo"] = "[Antwortadresse entfernt]";
            if ($message["Subject"] && strlen($message["Subject"]) > 0)
                $message_new["Subject"] = substr($this->dummy_text, 0, strlen($message["Subject"]));
            if ($message["Text"] && strlen($message["Text"]) > 0)
                $message_new["Text"] = substr($this->dummy_text, 0, strlen($message["Text"]));
            $this->socket->update_record_matched($efaCloudUserID, "efa2messages", "MessageId", $message_new, true);
        }
        // efa2sessiongroups has no texts besides the sessiongroup name
        // efa2waters has no person related texts
    }

    /**
     * Replace all names in the efa2persons table by an automatically generated arbitrary name. (Mae
     * / female is ignored)
     *
     * @param int $efaCloudUserID
     *            The efaCloudUserID, to autorise the data change.
     */
    private function anonymize_persons (int $efaCloudUserID)
    {
        // read all persons
        $persons = $this->socket->find_records("efa2persons", "", "", 5000, true);
        // create a person Id => name array, to ensure that the same Id always gets the same name.
        $new_person_names = [];
        $mn = 2134;
        $birthday = 1942;
        foreach ($persons as $person) {
            $Id = $person["Id"];
            $new_person = [];
            $new_person["Id"] = $Id;
            // generate a new name, if for this Id no name was generated before
            if (! $new_person_names[$Id] || strlen($new_person_names[$Id]) < 5) {
                $new_name = $this->next_name();
                $new_person_names[$Id] = $new_name;
                $new_person["FirstName"] = explode(" ", $new_name)[0];
                $new_person["LastName"] = explode(" ", $new_name)[1];
                $new_person["InputShortcut"] = "";
                $new_person["Email"] = "";
                $new_person["FreeUse"] = "";
                $new_person["FreeUse1"] = "";
                $new_person["FreeUse2"] = "";
                $new_person["FreeUse3"] = "";
                $new_person["MembershipNo"] = $mn;
                $new_person["Birthday"] = $birthday;
                $mn ++;
                $birthday ++;
                if ($birthday > 2002)
                    $birthday = 1952;
                $new_person["Association"] = 1;
                // This will update all data sets with the respective GUID.
                $this->socket->update_record_matched($efaCloudUserID, "efa2persons", "Id", $new_person, true);
                // fill a cache of Ids which will be use to shift the rowers later. That will keep
                // busy rowers busy, but split up the typical co-rowers.
                if (count($this->shift_cache) < 23)
                    $this->shift_cache[] = $Id;
            }
        }
    }

    /**
     * Replace all names in the efa2boats table by a name of a list of 145 German and French rivers.
     *
     * @param int $efaCloudUserID
     *            The efaCloudUserID, to autorise the data change.
     */
    private function anonymize_boat_names (int $efaCloudUserID)
    {
        // read all boats, but not more than 145 (length of rivers list.
        $boats = $this->socket->find_records("efa2boats", "", "", 145, true);
        // create a boat Id => name array, to ensure that the same Id always gets the same name.
        $new_boat_names = [];
        $riverindex = 0;
        foreach ($boats as $boat) {
            $Id = $boat["Id"];
            $new_boat = [];
            $new_boat["Id"] = $Id;
            // generate a new name, if for this Id no name was generated before
            if (! isset($new_boat_names[$Id])) {
                $new_name = $this->boat_names[$riverindex];
                $new_boat_names[$Id] = $new_name;
                $riverindex ++;
            } else {
                $new_name = $new_boat_names[$Id];
            }
            $new_boat["Name"] = $new_name;
            $this->socket->update_record_matched($efaCloudUserID, "efa2boats", "Id", $new_boat, true);
        }
    }

    /**
     * Shift all crew ids by 23 places. Fill the first 23 arbitrarily (Shift cache is filled in
     * anonymize_persons. Replace all crew names by "Gast".
     *
     * @param int $efaCloudUserID
     *            The efaCloudUserID, to autorise the data change.
     */
    private function shift_crews (int $efaCloudUserID)
    {
        $trips = $this->socket->find_records("efa2logbook", "", "", 5000, true);
        foreach ($trips as $trip) {
            $trip_new = [];
            $trip_new["EntryId"] = $trip["EntryId"];
            foreach ($this->crew_names as $crew_name)
                if (isset($trip[$crew_name]) && (strlen($trip[$crew_name]) > 0))
                    $trip_new[$crew_name] = "Gast";
            foreach ($this->crew_ids as $crew_id)
                if (isset($trip[$crew_id]) && (strlen($trip[$crew_id]) > 20)) {
                    $this->shift_cache[] = $trip[$crew_id];
                    $trip_new[$crew_id] = array_shift($this->shift_cache);
                }
            $this->socket->update_record_matched($efaCloudUserID, "efa2logbook", "EntryId", $trip_new, true);
        }
    }
}
    