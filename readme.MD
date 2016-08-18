# empleadoEstatalBot

## Que es?

Es un bot medio berretón de reddit que busca los posts de ciertos diarios, toma el texto y lo postea como un comentario en el link original.

## Que diarios soporta?

Por ahora la lista de diarios que parsea correctamente son:

- La Nacion
- Clarin
- IECO Clarin
- Infobae
- Cronista
- Telam
- Buenos Aires Herald
- Pagina 12
- Minuto Uno

## Como bajo e instalo el proyecto?

Create un fork y clonalo, o bajalo como zip. En la consola pone composer install. Agarra el config.sample.php, renombralo a config.php y pone tus credenciales.

## Como puedo agregar un diario nuevo?

Primero tenes que definirlo dentro de $newspapers en bot.php. El nombre tiene que ser el que devuelve reddit en el key hostname. Generalmente es el dominio sin el www adelante.
Luego vas a inc/newspaperParser.php y agregas una clase nueva a la interfaz que se llame igual que el hostname, pero sin puntos y terminado en ...Parser. Ahi invocas a Cthulhu, parseas el texto como mas te guste, asegurandote de devolver un html con h1, h2 y texto mas la firma del bot.

## Por que el codigo es tan horrible?

Por que soy un pesimo programador. Malagradecido.

## Podrias haber puesto algun comentario en las miles de lineas de codigo, eh

Cierto, pero me dió fiaca.