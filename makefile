CC=g++
CFLAGS=-O
LFLAGS=


all: trafplan

trafplan: main.cpp functions.h
	$(CC) $(CFLAGS) $(LFLAGS) main.cpp -o $@

clean:
	rm -f trafplan *~ *.o

