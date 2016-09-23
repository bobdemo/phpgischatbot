Cartella Lines:
---------------
Contiene i shapefile dei sentieri ottenuti dai track nei gpx del sito sardegna 
sentieri. I track gpx sono stati corretti e semplificati utilizzando Qgis ottenendo
così delle polyline valide in 2d. 
I dati di elevazione nei trackpoint del gps non sono stati inseriti perchè talvolta
 erano mancanti o errati.
Ogni shapefile contiene una linea e nel nome contiene il codice cai del sentiero

Cartella Points:
----------------
Contiene i shapefile con i vertici delle polyline ricavate dai gpx.
Ogni shapefile contiene i vertici di una linea e nel nome del file è presente  il codice cai del sentiero

Layer: zone1-pathways.shp
---------------------------------------
Contiene un layer gis con le linee dei sentieri è il merge dei shapefile nella 
cartella lines.
L'export pathway.sql contiene le istruzioni sql per il caricamento di questo layer
nella tabella pathway su postgresql.

Layer: zone1-points.shp
---------------------------------------
Contiene un layer gis con i vertici delle linee dei sentiero è il merge dei shapefile
nella cartella points.
L'export vertices.sql contiene le istruzioni sql per il caricamento di questo layer
nella tabella vertices su postgresql.
La tabella vertices è utile unicamente alla creazione del network per la costruzione
degli archi 2.5d ed il calcolo distanze

