<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Province;
use Illuminate\Database\Seeder;

class ProvinceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countryNames = [
            'Argentina', 'Bolivia', 'Brasil', 'Canadá', 'Chile', 'Colombia',
            'Costa Rica', 'Cuba', 'Ecuador', 'El Salvador', 'Estados Unidos',
            'Guatemala', 'Honduras', 'México', 'Nicaragua', 'Panamá',
            'Paraguay', 'Perú', 'Puerto Rico', 'República Dominicana',
            'Uruguay', 'Venezuela',
        ];

        $citiesByCountry = [
            'Argentina' => ['Buenos Aires', 'CABA', 'Catamarca', 'Chaco', 'Chubut', 'Córdoba',
                'Corrientes', 'Entre Ríos', 'Formosa', 'Jujuy', 'La Pampa', 'La Rioja',
                'Mendoza', 'Misiones', 'Neuquén', 'Río Negro', 'Salta', 'San Juan',
                'San Luis', 'Santa Cruz', 'Santa Fe', 'Santiago del Estero',
                'Tierra del Fuego', 'Tucumán'],
            'Bolivia' => ['La Paz', 'Santa Cruz de la Sierra', 'Cochabamba', 'Sucre', 'Oruro', 'Tarija', 'Potosí', 'Sacaba', 'Quillacollo', 'Montero'],
            'Brasil' => ['Brasilia', 'São Paulo', 'Río de Janeiro', 'Salvador', 'Fortaleza', 'Belo Horizonte', 'Manaos', 'Curitiba', 'Recife', 'Porto Alegre', 'Belém'],
            'Canadá' => ['Ottawa', 'Toronto', 'Montreal', 'Vancouver', 'Calgary', 'Edmonton', 'Quebec City', 'Winnipeg', 'Hamilton', 'London'],
            'Chile' => ['Santiago', 'Valparaíso', 'Concepción', 'La Serena', 'Antofagasta', 'Temuco', 'Rancagua', 'Talca', 'Arica', 'Puerto Montt'],
            'Colombia' => ['Bogotá', 'Medellín', 'Cali', 'Barranquilla', 'Cartagena', 'Cúcuta', 'Bucaramanga', 'Pereira', 'Santa Marta', 'Ibagué', 'Villavicencio', 'Manizales'],
            'Costa Rica' => ['San José', 'Alajuela', 'Heredia', 'Cartago', 'Liberia', 'Puntarenas', 'Limón', 'Guápiles', 'San Isidro de El General'],
            'Cuba' => ['La Habana', 'Santiago de Cuba', 'Camagüey', 'Holguín', 'Guantánamo', 'Santa Clara', 'Las Tunas', 'Bayamo', 'Cienfuegos', 'Matanzas'],
            'Ecuador' => ['Quito', 'Guayaquil', 'Cuenca', 'Santo Domingo', 'Machala', 'Manta', 'Portoviejo', 'Loja', 'Ambato', 'Esmeraldas', 'Quevedo'],
            'El Salvador' => ['San Salvador', 'Santa Ana', 'San Miguel', 'Santa Tecla', 'Mejicanos', 'Apopa', 'Delgado', 'Sonsonate', 'San Marcos'],
            'Estados Unidos' => ['Washington D.C.', 'New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix', 'Philadelphia', 'San Antonio', 'San Diego', 'Dallas', 'Miami', 'Atlanta'],
            'Guatemala' => ['Ciudad de Guatemala', 'Mixco', 'Quetzaltenango', 'Villa Nueva', 'Escuintla', 'Amatitlán', 'Chinautla', 'Cobán', 'Chimaltenango'],
            'Honduras' => ['Tegucigalpa', 'San Pedro Sula', 'Choloma', 'La Ceiba', 'El Progreso', 'Villanueva', 'Choluteca', 'Comayagua', 'Puerto Cortés'],
            'México' => ['Ciudad de México', 'Guadalajara', 'Monterrey', 'Puebla', 'Toluca', 'Tijuana', 'León', 'Ciudad Juárez', 'Torreón', 'Querétaro', 'Mérida', 'San Luis Potosí'],
            'Nicaragua' => ['Managua', 'León', 'Masaya', 'Tipitapa', 'Chinandega', 'Matagalpa', 'Estelí', 'Granada', 'Ciudad Sandino', 'Puerto Cabezas'],
            'Panamá' => ['Ciudad de Panamá', 'David', 'Colón', 'La Chorrera', 'Santiago de Veraguas', 'Arraiján', 'Chitré', 'Penonomé', 'Aguadulce'],
            'Paraguay' => ['Asunción', 'Ciudad del Este', 'Luque', 'San Lorenzo', 'Capiatá', 'Lambaré', 'Fernando de la Mora', 'Limpio', 'Ñemby', 'Encarnación'],
            'Perú' => ['Lima', 'Arequipa', 'Trujillo', 'Chiclayo', 'Piura', 'Iquitos', 'Cusco', 'Chimbote', 'Huancayo', 'Tacna', 'Juliaca', 'Ica'],
            'Puerto Rico' => ['San Juan', 'Bayamón', 'Carolina', 'Ponce', 'Caguas', 'Guaynabo', 'Mayagüez', 'Trujillo Alto', 'Arecibo', 'Fajardo'],
            'República Dominicana' => ['Santo Domingo', 'Santiago de los Caballeros', 'Santo Domingo Este', 'La Romana', 'San Pedro de Macorís', 'San Cristóbal', 'Puerto Plata', 'Higüey'],
            'Uruguay' => ['Montevideo', 'Salto', 'Ciudad de la Costa', 'Paysandú', 'Las Piedras', 'Rivera', 'Maldonado', 'Tacuarembó', 'Melo', 'Artigas'],
            'Venezuela' => ['Caracas', 'Maracaibo', 'Valencia', 'Barquisimeto', 'Maracay', 'Ciudad Guayana', 'Barcelona', 'Maturín', 'Mérida', 'San Cristóbal', 'Cumaná', 'Barinas', 'Punto Fijo', 'Coro', 'Valera'],
        ];

        foreach ($countryNames as $name) {

            $country = Country::query()->where('name', $name)->first();

            if ($country && isset($citiesByCountry[$name])) {
                foreach ($citiesByCountry[$name] as $cityName) {

                    Province::query()->create([
                        'country_id' => $country->id,
                        'name' => $cityName,
                        'is_active' => true,
                    ]);
                }
            }
        }
    }
}
