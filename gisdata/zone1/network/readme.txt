Layer: arcs.csv

- desrizione: 
derivato dalle intersezioni e sovrapposizioni dei sentieri contiene i campi:
  "from" id di un nodo
  "to"   id di un nodo 
  "sentiero" id del sentiero da cui recuperare la geometria
  "sentieri" id dei sentieri a cui appartiene separati da '_'
che permettono la costruzione degli archi del network su postgis

--------------------------------------------------------------------------------
Layer: nodes.shp

- desrizione: 
derivato dalle intersezioni e sovrapposizioni dei sentieri contiene i nodi del network
la tabella contiene i campi:
  "id" id del nodo
  "elev"   id di un nodo 
  "long" longitudine del punto
  "lat" latitudine del punto
le geometrie sono 2d. Il file nodes.sql contiene il file di esportazione su db 

