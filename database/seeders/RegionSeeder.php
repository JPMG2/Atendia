<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            'Argentina' => [
                'Buenos Aires' => ['La Plata', 'Mar del Plata', 'Bahía Blanca', 'Lanús', 'Quilmes', 'San Isidro'],
                'CABA' => ['Palermo', 'Recoleta', 'Belgrano', 'Puerto Madero', 'Caballito', 'San Telmo', 'Flores'],
                'Neuquén' => ['Centro', 'Confluencia', 'Santa Genoveva', 'Huilliches', 'Canal V'],
                'Río Negro' => ['Cipolletti', 'General Roca', 'Bariloche', 'Viedma', 'Villa Regina'],
                'Córdoba' => ['Nueva Córdoba', 'Cerro de las Rosas', 'General Paz', 'Alta Córdoba', 'Güemes'],
                'Mendoza' => ['Godoy Cruz', 'Guaymallén', 'Luján de Cuyo', 'Maipú', 'Chacras de Coria'],
                'Santa Fe' => ['Rosario', 'Santa Fe Capital', 'Rafaela', 'Venado Tuerto'],
                'Tucumán' => ['San Miguel', 'Yerba Buena', 'Tafí Viejo'],
            ],
            'Venezuela' => [
                'Caracas' => ['Chacao', 'Baruta', 'Libertador', 'Sucre', 'El Hatillo', 'Catia', 'Petare'],
                'Maracaibo' => ['La Limpia', 'San Francisco', 'Santa Lucía', 'Bella Vista', 'Indio Mara'],
                'Mérida' => ['Milla', 'Sagrario', 'Juan Rodríguez Suárez', 'Osuna Rodríguez',
                    'Arias', 'El Valle', 'Lasso de la Vega', 'Mariano Picón Salas',
                    'Antonio Spinetti Dini', 'Caracciolo Parra Pérez', 'Jacinto Plaza',
                    'Domingo Peña', 'La Hechicera', 'Los Cármenes', 'Santa Juana',
                    'Campo de Oro', 'La Parroquia', 'San Jacinto'],
                'Valencia' => ['Naguanagua', 'San Diego', 'Prebo', 'Los Guayos', 'Flor Amarillo'],
                'Barquisimeto' => ['Cabudare', 'Pueblo Nuevo', 'Santa Rosa', 'El Obelisco'],
                'Maracay' => ['El Limón', 'Cagua', 'Turmero', 'Caña de Azúcar'],
                'San Cristóbal' => ['Barrio Obrero', 'La Concordia', 'Pueblo Nuevo', 'Paramillo'],
            ],
            'Colombia' => [
                'Bogotá' => ['Usaquén', 'Chapinero', 'Santa Fe', 'Engativá', 'Suba', 'Fontibón', 'Kennedy'],
                'Medellín' => ['El Poblado', 'Laureles', 'Belén', 'Envigado', 'Sabaneta', 'Robledo', 'Aranjuez'],
                'Cali' => ['Pance', 'Ciudad Jardín', 'Aguablanca', 'San Antonio', 'Siloe'],
                'Barranquilla' => ['Riomar', 'Norte-Centro Histórico', 'Sur Occidente', 'Metropolitana'],
                'Cartagena' => ['Bocagrande', 'Getsemaní', 'Manga', 'Pie de la Popa', 'Castillogrande'],
                'Bucaramanga' => ['Cabecera del Llano', 'Real de Minas', 'Provenza', 'Cañaveral'],
            ],
            'México' => [
                'Ciudad de México' => ['Polanco', 'Coyoacán', 'Condesa', 'Tlalpan', 'Iztapalapa', 'Santa Fe'],
                'Guadalajara' => ['Zapopan', 'Tlaquepaque', 'Tonalá', 'Puerta de Hierro', 'Providencia'],
                'Monterrey' => ['San Pedro Garza García', 'San Nicolás', 'Guadalupe', 'Apodaca', 'Santa Catarina'],
                'Mérida' => ['Altabrisa', 'Montejo', 'Caucel', 'Cholul', 'Las Américas', 'Chuburná'],
                'Puebla' => ['Angelópolis', 'Cholula', 'Centro Histórico', 'La Paz'],
            ],
            'Chile' => [
                'Santiago' => ['Las Condes', 'Providencia', 'Vitacura', 'Maipú', 'Puente Alto', 'Santiago Centro'],
                'Valparaíso' => ['Viña del Mar', 'Quilpué', 'Villa Alemana', 'Concón'],
                'Concepción' => ['Talcahuano', 'San Pedro de la Paz', 'Chiguayante'],
            ],
            'Bolivia' => [
                'La Paz' => ['Zona Sur', 'Sopocachi', 'Miraflores', 'Calacoto', 'Achumani'],
                'Santa Cruz de la Sierra' => ['Equipetrol', 'Plan Tres Mil', 'Los Lotes', 'La Guardia', 'Warnes'],
                'Cochabamba' => ['Cala Cala', 'Querubines', 'La Chimba', 'Sarco'],
            ],
            'Brasil' => [
                'São Paulo' => ['Itaim Bibi', 'Pinheiros', 'Vila Madalena', 'Moema', 'Morumbi', 'Liberdade'],
                'Río de Janeiro' => ['Copacabana', 'Ipanema', 'Leblon', 'Barra da Tijuca', 'Botafogo', 'Flamengo'],
                'Brasilia' => ['Asa Sul', 'Asa Norte', 'Lago Sul', 'Lago Norte', 'Guará'],
            ],
            'Perú' => [
                'Lima' => ['Miraflores', 'San Isidro', 'Barranco', 'Surco', 'La Molina', 'Los Olivos'],
                'Arequipa' => ['Yanahuara', 'Cayma', 'Paucarpata', 'Selva Alegre'],
                'Trujillo' => ['Víctor Larco Herrera', 'Huanchaco', 'El Porvenir'],
            ],
            'Ecuador' => [
                'Quito' => ['Cumbayá', 'La Carolina', 'Guamaní', 'Quitumbe', 'El Condado'],
                'Guayaquil' => ['Samborondón', 'Ceibos', 'Urdesa', 'Puerto Santa Ana'],
                'Cuenca' => ['El Ejido', 'Totoracocha', 'Yanuncay'],
            ],
            'Costa Rica' => [
                'San José' => ['Escazú', 'Santa Ana', 'Pavas', 'Curridabat', 'San Pedro', 'Desamparados'],
                'Alajuela' => ['San Rafael', 'Guácima', 'San Antonio'],
                'Heredia' => ['Barreal', 'San Francisco', 'Belén'],
            ],
            'Uruguay' => [
                'Montevideo' => ['Pocitos', 'Carrasco', 'Punta Carretas', 'Malvín', 'Cordón', 'Aguada'],
                'Salto' => ['Cerro', 'Artigas', 'Salto Nuevo'],
            ],
            'Panamá' => [
                'Ciudad de Panamá' => ['San Francisco', 'Bella Vista', 'Costa del Este', 'Bethania', 'Ancón'],
                'David' => ['Las Lomas', 'San Carlos', 'Pedregal'],
            ],
            'Estados Unidos' => [
                'New York' => ['Manhattan', 'Brooklyn', 'Queens', 'The Bronx', 'Staten Island'],
                'Los Angeles' => ['Hollywood', 'Santa Monica', 'Beverly Hills', 'Venice', 'Downtown LA'],
                'Miami' => ['Brickell', 'Coconut Grove', 'Wynwood', 'Little Havana', 'Coral Gables'],
                'Chicago' => ['The Loop', 'Lincoln Park', 'Hyde Park', 'Wicker Park', 'Logan Square'],
            ],
            'Canadá' => [
                'Toronto' => ['North York', 'Scarborough', 'Etobicoke', 'Downtown', 'York'],
                'Montreal' => ['Ville-Marie', 'Plateau Mont-Royal', 'Saint-Laurent', 'Lachine'],
                'Vancouver' => ['Burnaby', 'Richmond', 'Surrey', 'Coquitlam'],
            ],
            'Cuba' => [
                'La Habana' => ['Playa', 'Plaza de la Revolución', 'Centro Habana', 'Habana Vieja', 'Miramar'],
                'Santiago de Cuba' => ['Vista Alegre', 'Santa Bárbara', 'El Caney'],
            ],
            'Nicaragua' => [
                'Managua' => ['Bello Horizonte', 'Bolonia', 'Altamira', 'Los Robles', 'Villa Fontana'],
                'León' => ['Sutiaba', 'El Sagrario', 'San Juan'],
            ],
            'El Salvador' => [
                'San Salvador' => ['Antiguo Cuscatlán', 'Escalón', 'San Benito', 'Santa Elena'],
                'Santa Ana' => ['El Palmar', 'Santa Lucía'],
            ],
            'Puerto Rico' => [
                'San Juan' => ['Condado', 'Santurce', 'Hato Rey', 'Viejo San Juan'],
                'Bayamón' => ['Rexville', 'Lomas Verdes', 'Santa Juanita'],
            ],
            'Paraguay' => [
                'Asunción' => ['Villa Morra', 'Carmelitas', 'Sajonia', 'Barrio Obrero'],
                'Ciudad del Este' => ['Km 7', 'Km 8', 'Area 1'],
            ],
            'República Dominicana' => [
                'Santo Domingo' => ['Piantini', 'Naco', 'Gazcue', 'Bella Vista', 'Arroyo Hondo'],
                'Santiago de los Caballeros' => ['Los Jardines', 'Villa Olga', 'Gurabo'],
            ],
            'Honduras' => [
                'Tegucigalpa' => ['Lomas del Guijarro', 'Hato de Enmedio', 'El Hatillo'],
                'San Pedro Sula' => ['Barrio Guamilito', 'Barrio Los Andes', 'Chamelecón'],
            ],
            'Guatemala' => [
                'Ciudad de Guatemala' => ['Zona 10', 'Zona 14', 'Zona 1', 'Zona 4', 'Cayalá'],
                'Quetzaltenango' => ['Zona 1', 'Zona 3', 'Baños del Inca'],
            ],
        ];

        foreach ($data as $countryName => $cities) {
            $country = Country::query()->where('name', $countryName)->first();

            if ($country) {
                foreach ($cities as $provinceName => $locations) {
                    $province = Province::query()->where('name', $provinceName)
                        ->where('country_id', $country->id)
                        ->first();

                    if ($province) {
                        foreach ($locations as $locationName) {
                            Region::query()->updateOrCreate([
                                'province_id' => $province->id,
                                'name' => $locationName,
                            ]);
                        }
                    }
                }
            }
        }
    }
}
