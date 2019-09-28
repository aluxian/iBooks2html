sqlite3 $HOME/Library/Containers/com.apple.iBooksX/Data/Documents/BKLibrary/BKLibrary*.sqlite "SELECT ZASSETID, ZTITLE, ZAUTHOR FROM ZBKLIBRARYASSET WHERE ZTITLE IS NOT NULL" -csv -header > books.csv
