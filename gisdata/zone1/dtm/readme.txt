Layer: DTM10  (da aggiungere )

- desrizione: 
dtm passo 10 metri dell’area di gutturru manu

- srid  3857

- genealogia: 
il dtm è stato ottenuto dal merge dei tasselli 556 e 565 del dtm passo 10m della 
regione sardegna poi riproiettato su wgs84 mercatore  EPSG:3857
 (http://www.sardegnageoportale.it/index.php?xsl=1598&s=161573&v=2&c=8936&t=1)

- uso nel progetto
il dtm è il sorgente del dato di elevazione dei vertici delle polyline dei sentieri 
e dei nodi del network.
L'altimetria è aggiunta alle tabelle dei shapefile dei dati nelle cartelle network 
e sentieri tramite il plugin di QGis "Point sampling tools". 
La creazione delle geometrie 2.5d avviene su postgis dopo il caricamento dei shapefile 