Contiene le tile per la zone1 di google o altro servizio (sarebbe bene usare 
quelle della regione )

Secondo la logica Slippy map tilenames bisogna identificare le coordinate x,y 
delle tile (file .png) ai livelli di zoom che si vuole usare

L'area di Gutturru mannu è contenuta in una sola tile al livello di zoom 9 
alle  coordinate x:268 , y:195

Usando google la tile (file png di circa 15kb) è recuperabile all'indirizzo: 
    https://mts1.google.com/vt/lyrs=s&hl=x-local&src=app&x=268&y=195&z=9

Al livello di zoom 10 le tile sono 4:

(268*2, 195*2) ; (268*2+1 195*2) ; (268*2, 195*2+1) ; (268*2+1, 195*2+1)

Al livello di zoom 11 le tile sono 4 * 4 = 16:
Al livello di zoom 12 le tile sono 16 * 4 = 64: 

Cioè al livello di zoom z > 9 le tile sono 4 elevato (z-9). 
al livello 15 sono 16384 file di circa 10kb
il livello 16 incomuicia ad essere proibitivo


(solo offline)