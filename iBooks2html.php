<?php
 
define('RESULT_DIRECTORY_NAME', "Exported");
define('BOOKS_DATABASE_DIRECTORY', getenv("HOME") . '/Library/Containers/com.apple.iBooksX/Data/Documents/BKLibrary');
define('NOTES_DATABASE_DIRECTORY', getenv("HOME") . '/Library/Containers/com.apple.iBooksX/Data/Documents/AEAnnotation');

if (file_exists(RESULT_DIRECTORY_NAME)) {
    die("The destination folder for the exports already exists on your Mac.\nPlease move that one out of the way before proceeding.\n");
}

if (!file_exists(BOOKS_DATABASE_DIRECTORY)) {
    die("Sorry, couldn't find an iBooks Library on your Mac. Have you put any books in there?\n");
} else {
    if (!$path = exec('ls '.BOOKS_DATABASE_DIRECTORY."/*.sqlite")) {
        die("Could not find the iBooks library database. Have you put any books in there?\n");
    } else {
        define('BOOKS_DATABASE_FILE', $path);
    }
}


if (!file_exists(NOTES_DATABASE_DIRECTORY)) {
    die("Sorry, couldn't find any iBooks notes on your Mac. Have you actually taken any notes in iBooks?\n");
} else {
    if (!$path = exec('ls '.NOTES_DATABASE_DIRECTORY."/*.sqlite")) {
        die("Could not find the iBooks notes database. Have you actually taken any notes in iBooks?\n");
    } else {
        define('NOTES_DATABASE_FILE', $path);
    }
}


class MyDB extends SQLite3
{
    public function __construct($FileName)
    {
        $this->open($FileName);
    }
}


$books = array();

$booksdb = new MyDB(BOOKS_DATABASE_FILE);

if (!$booksdb) {
    echo $booksdb->lastErrorMsg();
}

$res = $booksdb->query("SELECT ZASSETID, ZTITLE AS Title, ZAUTHOR AS Author FROM ZBKLIBRARYASSET WHERE ZTITLE IS NOT NULL");

while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $books[$row['ZASSETID']] = $row;
}

$booksdb->close();

if (count($books)==0) {
    die("No books found in your library. Have you added any to iBooks?\n");
}

$notesdb = new MyDB(NOTES_DATABASE_FILE);

if (!$notesdb) {
    echo $notesdb->lastErrorMsg();
}

$notes = array();

$res = $notesdb->query("SELECT
				ZANNOTATIONREPRESENTATIVETEXT as BroaderText,
				ZANNOTATIONSELECTEDTEXT as SelectedText,
				ZANNOTATIONNOTE as Note,
				ZFUTUREPROOFING5 as Chapter,
				ZANNOTATIONCREATIONDATE as Created,
				ZANNOTATIONMODIFICATIONDATE as Modified,
				ZANNOTATIONASSETID
			FROM ZAEANNOTATION
			WHERE ZANNOTATIONSELECTEDTEXT IS NOT NULL
			ORDER BY ZANNOTATIONASSETID ASC,Created ASC");

while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $notes[$row['ZANNOTATIONASSETID']][] = $row;
}

$notesdb->close();

if (count($notes)==0) {
    die("No notes found in your library. Have you added any to iBooks?\n\nIf you did on other devices than this Mac, make sure to enable iBooks notes/bookmarks syncing on all devices.");
}

mkdir(RESULT_DIRECTORY_NAME);
chdir(RESULT_DIRECTORY_NAME);

$i=0;
$j=0;
$b=0;

foreach ($notes as $AssetID => $booknotes) {
    $Body = '';
    $BookTitle = $books[$AssetID]['Title'];
    
    $j = 0;

    foreach ($booknotes as $note) {
        $TextWithContext = null;
                
        $HighlightedText = $note['SelectedText'];
        
        // If iBooks stored the surrounding paragraph of a highlighted text, show it and make the highlighted text show as highlighted.
        if (!empty($note['BroaderText']) && $note['BroaderText'] != $note['SelectedText']) {
            $TextWithContext = str_replace($note['SelectedText'], "<span style=\"background: yellow;\">".$note['SelectedText']."</span>", $note['BroaderText']);
        }
        
        // Keep some counters for commandline feedback
        if ($j==0) {
            $b++;
        }
        $i++;
        $j++;
        
        $Body .='
<note><title>'.($HighlightedText).'</title><content>
<div>
<p>'.($TextWithContext?$TextWithContext:$HighlightedText).'</p>
<p><span style="color: rgb(169, 169, 169);font-size: 12px;">From chapter: '.$note['Chapter'].'</span></p>
</div>
<div>'.$note['Note'].'</div>
</content><created>'.@strftime('%Y%m%dT%H%M%S', @strtotime("2001-01-01 +". ((int)$note['Created'])." seconds")).'</created><updated>'.@strftime('%Y%m%dT%H%M%S', @strtotime("2001-01-01 +". ((int)$note['Modified'])." seconds")).'</updated></note>';
    }
    
    file_put_contents($BookTitle.".html", $Body);
}

echo "Done! Exported $i notes into $b separate export files in the '".RESULT_DIRECTORY_NAME."' folder.\n\n";
