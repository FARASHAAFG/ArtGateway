INSERT INTO artists (name, birthYear, deathYear, nationality) VALUES
('Claude Monet', 1840, 1926, 'French'),
('Vincent van Gogh', 1853, 1890, 'Dutch');

INSERT INTO museums (name, city, country, website) VALUES
('Musée d''Orsay', 'Paris', 'France', 'https://www.musee-orsay.fr'),
('Van Gogh Museum', 'Amsterdam', 'Netherlands', 'https://www.vangoghmuseum.nl');

INSERT INTO artworks (title, creationYear, medium, period, artistId, museumId) VALUES
('Poppies', 1873, 'Oil on canvas', 'Impressionism', 1, 1),
('Sunflowers', 1889, 'Oil on canvas', 'Post-Impressionism', 2, 2),
('The Bedroom', 1888, 'Oil on canvas', 'Post-Impressionism', 2, 2);

INSERT INTO tags (name) VALUES ('landscape'), ('flowers');
INSERT INTO artwork_tags (artworkId, tagId) VALUES (1, 1), (1, 2), (2, 2);