<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class GeoCatalogSeeder extends Seeder
{
    /**
     * Canonical Central American departments catalog.
     *
     * @return list<array{Id_departamento: int, Departamento: string, Id_pais: int}>
     */
    public static function getDepartamentos(): array
    {
        return [
            // El Salvador (Id_pais = 1, requiere_distrito = 1)
            ['Id_departamento' => 1, 'Departamento' => 'Ahuachapán', 'Id_pais' => 1],
            ['Id_departamento' => 2, 'Departamento' => 'Santa Ana', 'Id_pais' => 1],
            ['Id_departamento' => 3, 'Departamento' => 'Sonsonate', 'Id_pais' => 1],
            ['Id_departamento' => 4, 'Departamento' => 'Chalatenango', 'Id_pais' => 1],
            ['Id_departamento' => 5, 'Departamento' => 'La Libertad', 'Id_pais' => 1],
            ['Id_departamento' => 6, 'Departamento' => 'San Salvador', 'Id_pais' => 1],
            ['Id_departamento' => 7, 'Departamento' => 'Cuscatlán', 'Id_pais' => 1],
            ['Id_departamento' => 8, 'Departamento' => 'La Paz', 'Id_pais' => 1],
            ['Id_departamento' => 9, 'Departamento' => 'Cabañas', 'Id_pais' => 1],
            ['Id_departamento' => 10, 'Departamento' => 'San Vicente', 'Id_pais' => 1],
            ['Id_departamento' => 11, 'Departamento' => 'Usulután', 'Id_pais' => 1],
            ['Id_departamento' => 12, 'Departamento' => 'San Miguel', 'Id_pais' => 1],
            ['Id_departamento' => 13, 'Departamento' => 'Morazán', 'Id_pais' => 1],
            ['Id_departamento' => 14, 'Departamento' => 'La Unión', 'Id_pais' => 1],

            // Guatemala (Id_pais = 2, requiere_distrito = 0)
            ['Id_departamento' => 15, 'Departamento' => 'Alta Verapaz', 'Id_pais' => 2],
            ['Id_departamento' => 16, 'Departamento' => 'Baja Verapaz', 'Id_pais' => 2],
            ['Id_departamento' => 17, 'Departamento' => 'Chimaltenango', 'Id_pais' => 2],
            ['Id_departamento' => 18, 'Departamento' => 'Chiquimula', 'Id_pais' => 2],
            ['Id_departamento' => 19, 'Departamento' => 'El Progreso', 'Id_pais' => 2],
            ['Id_departamento' => 20, 'Departamento' => 'Escuintla', 'Id_pais' => 2],
            ['Id_departamento' => 21, 'Departamento' => 'Guatemala', 'Id_pais' => 2],
            ['Id_departamento' => 22, 'Departamento' => 'Huehuetenango', 'Id_pais' => 2],
            ['Id_departamento' => 23, 'Departamento' => 'Izabal', 'Id_pais' => 2],
            ['Id_departamento' => 24, 'Departamento' => 'Jalapa', 'Id_pais' => 2],
            ['Id_departamento' => 25, 'Departamento' => 'Jutiapa', 'Id_pais' => 2],
            ['Id_departamento' => 26, 'Departamento' => 'Petén', 'Id_pais' => 2],
            ['Id_departamento' => 27, 'Departamento' => 'Quetzaltenango', 'Id_pais' => 2],
            ['Id_departamento' => 28, 'Departamento' => 'Quiché', 'Id_pais' => 2],
            ['Id_departamento' => 29, 'Departamento' => 'Retalhuleu', 'Id_pais' => 2],
            ['Id_departamento' => 30, 'Departamento' => 'Sacatepéquez', 'Id_pais' => 2],
            ['Id_departamento' => 31, 'Departamento' => 'San Marcos', 'Id_pais' => 2],
            ['Id_departamento' => 32, 'Departamento' => 'Santa Rosa', 'Id_pais' => 2],
            ['Id_departamento' => 33, 'Departamento' => 'Sololá', 'Id_pais' => 2],
            ['Id_departamento' => 34, 'Departamento' => 'Suchitepéquez', 'Id_pais' => 2],
            ['Id_departamento' => 35, 'Departamento' => 'Totonicapán', 'Id_pais' => 2],
            ['Id_departamento' => 36, 'Departamento' => 'Zacapa', 'Id_pais' => 2],

            // Honduras (Id_pais = 3, requiere_distrito = 1)
            ['Id_departamento' => 37, 'Departamento' => 'Francisco Morazán', 'Id_pais' => 3],
            ['Id_departamento' => 38, 'Departamento' => 'Cortés', 'Id_pais' => 3],
            ['Id_departamento' => 39, 'Departamento' => 'Atlántida', 'Id_pais' => 3],
        ];
    }

    /**
     * Canonical Central American municipalities catalog.
     *
     * @return list<array{Id_municipio: int, Municipio: string, Id_departamento: int}>
     */
    public static function getMunicipios(): array
    {
        return [
            // El Salvador
            ['Id_municipio' => 1, 'Municipio' => 'Ahuachapán Norte', 'Id_departamento' => 1],
            ['Id_municipio' => 2, 'Municipio' => 'Ahuachapán Centro', 'Id_departamento' => 1],
            ['Id_municipio' => 3, 'Municipio' => 'Ahuachapán Sur', 'Id_departamento' => 1],

            ['Id_municipio' => 4, 'Municipio' => 'Santa Ana Norte', 'Id_departamento' => 2],
            ['Id_municipio' => 5, 'Municipio' => 'Santa Ana Centro', 'Id_departamento' => 2],
            ['Id_municipio' => 6, 'Municipio' => 'Santa Ana Este', 'Id_departamento' => 2],
            ['Id_municipio' => 7, 'Municipio' => 'Santa Ana Oeste', 'Id_departamento' => 2],

            ['Id_municipio' => 8, 'Municipio' => 'Sonsonate Norte', 'Id_departamento' => 3],
            ['Id_municipio' => 9, 'Municipio' => 'Sonsonate Centro', 'Id_departamento' => 3],
            ['Id_municipio' => 10, 'Municipio' => 'Sonsonate Este', 'Id_departamento' => 3],
            ['Id_municipio' => 11, 'Municipio' => 'Sonsonate Oeste', 'Id_departamento' => 3],

            ['Id_municipio' => 12, 'Municipio' => 'Chalatenango Norte', 'Id_departamento' => 4],
            ['Id_municipio' => 13, 'Municipio' => 'Chalatenango Centro', 'Id_departamento' => 4],
            ['Id_municipio' => 14, 'Municipio' => 'Chalatenango Sur', 'Id_departamento' => 4],

            ['Id_municipio' => 15, 'Municipio' => 'La Libertad Norte', 'Id_departamento' => 5],
            ['Id_municipio' => 16, 'Municipio' => 'La Libertad Centro', 'Id_departamento' => 5],
            ['Id_municipio' => 17, 'Municipio' => 'La Libertad Oeste', 'Id_departamento' => 5],
            ['Id_municipio' => 18, 'Municipio' => 'La Libertad Este', 'Id_departamento' => 5],
            ['Id_municipio' => 19, 'Municipio' => 'La Libertad Costa', 'Id_departamento' => 5],
            ['Id_municipio' => 20, 'Municipio' => 'La Libertad Sur', 'Id_departamento' => 5],

            ['Id_municipio' => 21, 'Municipio' => 'San Salvador Norte', 'Id_departamento' => 6],
            ['Id_municipio' => 22, 'Municipio' => 'San Salvador Oeste', 'Id_departamento' => 6],
            ['Id_municipio' => 23, 'Municipio' => 'San Salvador Este', 'Id_departamento' => 6],
            ['Id_municipio' => 24, 'Municipio' => 'San Salvador Centro', 'Id_departamento' => 6],
            ['Id_municipio' => 25, 'Municipio' => 'San Salvador Sur', 'Id_departamento' => 6],

            ['Id_municipio' => 26, 'Municipio' => 'Cuscatlán Norte', 'Id_departamento' => 7],
            ['Id_municipio' => 27, 'Municipio' => 'Cuscatlán Sur', 'Id_departamento' => 7],

            ['Id_municipio' => 28, 'Municipio' => 'La Paz Oeste', 'Id_departamento' => 8],
            ['Id_municipio' => 29, 'Municipio' => 'La Paz Centro', 'Id_departamento' => 8],
            ['Id_municipio' => 30, 'Municipio' => 'La Paz Este', 'Id_departamento' => 8],

            ['Id_municipio' => 31, 'Municipio' => 'Cabañas Oeste', 'Id_departamento' => 9],
            ['Id_municipio' => 32, 'Municipio' => 'Cabañas Este', 'Id_departamento' => 9],

            ['Id_municipio' => 33, 'Municipio' => 'San Vicente Norte', 'Id_departamento' => 10],
            ['Id_municipio' => 34, 'Municipio' => 'San Vicente Sur', 'Id_departamento' => 10],

            ['Id_municipio' => 35, 'Municipio' => 'Usulután Norte', 'Id_departamento' => 11],
            ['Id_municipio' => 36, 'Municipio' => 'Usulután Este', 'Id_departamento' => 11],
            ['Id_municipio' => 37, 'Municipio' => 'Usulután Oeste', 'Id_departamento' => 11],

            ['Id_municipio' => 38, 'Municipio' => 'San Miguel Norte', 'Id_departamento' => 12],
            ['Id_municipio' => 39, 'Municipio' => 'San Miguel Centro', 'Id_departamento' => 12],
            ['Id_municipio' => 40, 'Municipio' => 'San Miguel Oeste', 'Id_departamento' => 12],

            ['Id_municipio' => 41, 'Municipio' => 'Morazán Norte', 'Id_departamento' => 13],
            ['Id_municipio' => 42, 'Municipio' => 'Morazán Sur', 'Id_departamento' => 13],

            ['Id_municipio' => 43, 'Municipio' => 'La Unión Norte', 'Id_departamento' => 14],
            ['Id_municipio' => 44, 'Municipio' => 'La Unión Sur', 'Id_departamento' => 14],


            // Guatemala
            ['Id_municipio' => 45, 'Municipio' => 'Cobán', 'Id_departamento' => 15],
            ['Id_municipio' => 46, 'Municipio' => 'Chahal', 'Id_departamento' => 15],
            ['Id_municipio' => 47, 'Municipio' => 'Chisec', 'Id_departamento' => 15],
            ['Id_municipio' => 48, 'Municipio' => 'Lanquín', 'Id_departamento' => 15],
            ['Id_municipio' => 49, 'Municipio' => 'Panzós', 'Id_departamento' => 15],
            ['Id_municipio' => 50, 'Municipio' => 'Raxruhá', 'Id_departamento' => 15],
            ['Id_municipio' => 51, 'Municipio' => 'San Cristóbal Verapaz', 'Id_departamento' => 15],
            ['Id_municipio' => 52, 'Municipio' => 'San Juan Chamelco', 'Id_departamento' => 15],
            ['Id_municipio' => 53, 'Municipio' => 'San Miguel Tucurú', 'Id_departamento' => 15],
            ['Id_municipio' => 54, 'Municipio' => 'San Pedro Carchá', 'Id_departamento' => 15],
            ['Id_municipio' => 55, 'Municipio' => 'Santa Catarina La Tinta', 'Id_departamento' => 15],
            ['Id_municipio' => 56, 'Municipio' => 'Santa Cruz Verapaz', 'Id_departamento' => 15],
            ['Id_municipio' => 57, 'Municipio' => 'Santa María Cahabón', 'Id_departamento' => 15],
            ['Id_municipio' => 58, 'Municipio' => 'Senahú', 'Id_departamento' => 15],
            ['Id_municipio' => 59, 'Municipio' => 'Tactic', 'Id_departamento' => 15],
            ['Id_municipio' => 60, 'Municipio' => 'Tamahú', 'Id_departamento' => 15],
            ['Id_municipio' => 61, 'Municipio' => 'Fray Bartolomé de las Casas', 'Id_departamento' => 15],
            ['Id_municipio' => 62, 'Municipio' => 'Salamá', 'Id_departamento' => 16],
            ['Id_municipio' => 63, 'Municipio' => 'Cubulco', 'Id_departamento' => 16],
            ['Id_municipio' => 64, 'Municipio' => 'Granados', 'Id_departamento' => 16],
            ['Id_municipio' => 65, 'Municipio' => 'Purulhá', 'Id_departamento' => 16],
            ['Id_municipio' => 66, 'Municipio' => 'Rabinal', 'Id_departamento' => 16],
            ['Id_municipio' => 67, 'Municipio' => 'San Jerónimo', 'Id_departamento' => 16],
            ['Id_municipio' => 68, 'Municipio' => 'San Miguel Chicaj', 'Id_departamento' => 16],
            ['Id_municipio' => 69, 'Municipio' => 'Santa Cruz el Chol', 'Id_departamento' => 16],
            ['Id_municipio' => 70, 'Municipio' => 'Chimaltenango', 'Id_departamento' => 17],
            ['Id_municipio' => 71, 'Municipio' => 'Acatenango', 'Id_departamento' => 17],
            ['Id_municipio' => 72, 'Municipio' => 'El Tejar', 'Id_departamento' => 17],
            ['Id_municipio' => 73, 'Municipio' => 'Parramos', 'Id_departamento' => 17],
            ['Id_municipio' => 74, 'Municipio' => 'Patzicía', 'Id_departamento' => 17],
            ['Id_municipio' => 75, 'Municipio' => 'Patzún', 'Id_departamento' => 17],
            ['Id_municipio' => 76, 'Municipio' => 'Pochuta', 'Id_departamento' => 17],
            ['Id_municipio' => 77, 'Municipio' => 'San Andrés Itzapa', 'Id_departamento' => 17],
            ['Id_municipio' => 78, 'Municipio' => 'San José Poaquil', 'Id_departamento' => 17],
            ['Id_municipio' => 79, 'Municipio' => 'San Martín Jilotepeque', 'Id_departamento' => 17],
            ['Id_municipio' => 80, 'Municipio' => 'Santa Apolonia', 'Id_departamento' => 17],
            ['Id_municipio' => 81, 'Municipio' => 'Santa Cruz Balanyá', 'Id_departamento' => 17],
            ['Id_municipio' => 82, 'Municipio' => 'Tecpán Guatemala', 'Id_departamento' => 17],
            ['Id_municipio' => 83, 'Municipio' => 'Yepocapa', 'Id_departamento' => 17],
            ['Id_municipio' => 84, 'Municipio' => 'Zaragoza', 'Id_departamento' => 17],
            ['Id_municipio' => 85, 'Municipio' => 'Comalapa', 'Id_departamento' => 17],
            ['Id_municipio' => 86, 'Municipio' => 'Chiquimula', 'Id_departamento' => 18],
            ['Id_municipio' => 87, 'Municipio' => 'Camotán', 'Id_departamento' => 18],
            ['Id_municipio' => 88, 'Municipio' => 'Concepción Las Minas', 'Id_departamento' => 18],
            ['Id_municipio' => 89, 'Municipio' => 'Esquipulas', 'Id_departamento' => 18],
            ['Id_municipio' => 90, 'Municipio' => 'Ipala', 'Id_departamento' => 18],
            ['Id_municipio' => 91, 'Municipio' => 'Jocotán', 'Id_departamento' => 18],
            ['Id_municipio' => 92, 'Municipio' => 'Olopa', 'Id_departamento' => 18],
            ['Id_municipio' => 93, 'Municipio' => 'Quezaltepeque', 'Id_departamento' => 18],
            ['Id_municipio' => 94, 'Municipio' => 'San Jacinto', 'Id_departamento' => 18],
            ['Id_municipio' => 95, 'Municipio' => 'San José La Arada', 'Id_departamento' => 18],
            ['Id_municipio' => 96, 'Municipio' => 'San Juan Ermita', 'Id_departamento' => 18],
            ['Id_municipio' => 97, 'Municipio' => 'Guastatoya', 'Id_departamento' => 19],
            ['Id_municipio' => 98, 'Municipio' => 'El Jícaro', 'Id_departamento' => 19],
            ['Id_municipio' => 99, 'Municipio' => 'Morazán', 'Id_departamento' => 19],
            ['Id_municipio' => 100, 'Municipio' => 'San Agustín Acasaguastlán', 'Id_departamento' => 19],
            ['Id_municipio' => 101, 'Municipio' => 'San Antonio La Paz', 'Id_departamento' => 19],
            ['Id_municipio' => 102, 'Municipio' => 'San Cristóbal Acasaguastlán', 'Id_departamento' => 19],
            ['Id_municipio' => 103, 'Municipio' => 'Sanarate', 'Id_departamento' => 19],
            ['Id_municipio' => 104, 'Municipio' => 'Sansare', 'Id_departamento' => 19],
            ['Id_municipio' => 105, 'Municipio' => 'Escuintla', 'Id_departamento' => 20],
            ['Id_municipio' => 106, 'Municipio' => 'Guanagazapa', 'Id_departamento' => 20],
            ['Id_municipio' => 107, 'Municipio' => 'Iztapa', 'Id_departamento' => 20],
            ['Id_municipio' => 108, 'Municipio' => 'La Democracia', 'Id_departamento' => 20],
            ['Id_municipio' => 109, 'Municipio' => 'La Gomera', 'Id_departamento' => 20],
            ['Id_municipio' => 110, 'Municipio' => 'Masagua', 'Id_departamento' => 20],
            ['Id_municipio' => 111, 'Municipio' => 'Nueva Concepción', 'Id_departamento' => 20],
            ['Id_municipio' => 112, 'Municipio' => 'Palín', 'Id_departamento' => 20],
            ['Id_municipio' => 113, 'Municipio' => 'San José', 'Id_departamento' => 20],
            ['Id_municipio' => 114, 'Municipio' => 'San Vicente Pacaya', 'Id_departamento' => 20],
            ['Id_municipio' => 115, 'Municipio' => 'Santa Lucía Cotzumalguapa', 'Id_departamento' => 20],
            ['Id_municipio' => 116, 'Municipio' => 'Sipacate', 'Id_departamento' => 20],
            ['Id_municipio' => 117, 'Municipio' => 'Tiquisate', 'Id_departamento' => 20],
            ['Id_municipio' => 118, 'Municipio' => 'Siquinalá', 'Id_departamento' => 20],
            ['Id_municipio' => 119, 'Municipio' => 'Guatemala', 'Id_departamento' => 21],
            ['Id_municipio' => 120, 'Municipio' => 'Amatitlán', 'Id_departamento' => 21],
            ['Id_municipio' => 121, 'Municipio' => 'Chinautla', 'Id_departamento' => 21],
            ['Id_municipio' => 122, 'Municipio' => 'Chuarrancho', 'Id_departamento' => 21],
            ['Id_municipio' => 123, 'Municipio' => 'Fraijanes', 'Id_departamento' => 21],
            ['Id_municipio' => 124, 'Municipio' => 'Mixco', 'Id_departamento' => 21],
            ['Id_municipio' => 125, 'Municipio' => 'Palencia', 'Id_departamento' => 21],
            ['Id_municipio' => 126, 'Municipio' => 'San José del Golfo', 'Id_departamento' => 21],
            ['Id_municipio' => 127, 'Municipio' => 'San José Pinula', 'Id_departamento' => 21],
            ['Id_municipio' => 128, 'Municipio' => 'San Juan Sacatepéquez', 'Id_departamento' => 21],
            ['Id_municipio' => 129, 'Municipio' => 'San Miguel Petapa', 'Id_departamento' => 21],
            ['Id_municipio' => 130, 'Municipio' => 'San Pedro Ayampuc', 'Id_departamento' => 21],
            ['Id_municipio' => 131, 'Municipio' => 'San Pedro Sacatepéquez', 'Id_departamento' => 21],
            ['Id_municipio' => 132, 'Municipio' => 'San Raymundo', 'Id_departamento' => 21],
            ['Id_municipio' => 133, 'Municipio' => 'Santa Catarina Pinula', 'Id_departamento' => 21],
            ['Id_municipio' => 134, 'Municipio' => 'Villa Canales', 'Id_departamento' => 21],
            ['Id_municipio' => 135, 'Municipio' => 'Villa Nueva', 'Id_departamento' => 21],
            ['Id_municipio' => 136, 'Municipio' => 'Huehuetenango', 'Id_departamento' => 22],
            ['Id_municipio' => 137, 'Municipio' => 'Aguacatán', 'Id_departamento' => 22],
            ['Id_municipio' => 138, 'Municipio' => 'Chiantla', 'Id_departamento' => 22],
            ['Id_municipio' => 139, 'Municipio' => 'Colotenango', 'Id_departamento' => 22],
            ['Id_municipio' => 140, 'Municipio' => 'Concepción Huista', 'Id_departamento' => 22],
            ['Id_municipio' => 141, 'Municipio' => 'Cuilco', 'Id_departamento' => 22],
            ['Id_municipio' => 142, 'Municipio' => 'Jacaltenango', 'Id_departamento' => 22],
            ['Id_municipio' => 143, 'Municipio' => 'La Democracia', 'Id_departamento' => 22],
            ['Id_municipio' => 144, 'Municipio' => 'La Libertad', 'Id_departamento' => 22],
            ['Id_municipio' => 145, 'Municipio' => 'Malacatancito', 'Id_departamento' => 22],
            ['Id_municipio' => 146, 'Municipio' => 'Nentón', 'Id_departamento' => 22],
            ['Id_municipio' => 147, 'Municipio' => 'Petatán', 'Id_departamento' => 22],
            ['Id_municipio' => 148, 'Municipio' => 'San Antonio Huista', 'Id_departamento' => 22],
            ['Id_municipio' => 149, 'Municipio' => 'San Gaspar Ixchil', 'Id_departamento' => 22],
            ['Id_municipio' => 150, 'Municipio' => 'San Ildefonso Ixtahuacán', 'Id_departamento' => 22],
            ['Id_municipio' => 151, 'Municipio' => 'San Juan Atitán', 'Id_departamento' => 22],
            ['Id_municipio' => 152, 'Municipio' => 'San Juan Ixcoy', 'Id_departamento' => 22],
            ['Id_municipio' => 153, 'Municipio' => 'San Mateo Ixtatán', 'Id_departamento' => 22],
            ['Id_municipio' => 154, 'Municipio' => 'San Miguel Acatán', 'Id_departamento' => 22],
            ['Id_municipio' => 155, 'Municipio' => 'San Pedro Necta', 'Id_departamento' => 22],
            ['Id_municipio' => 156, 'Municipio' => 'San Pedro Soloma', 'Id_departamento' => 22],
            ['Id_municipio' => 157, 'Municipio' => 'San Rafael La Independencia', 'Id_departamento' => 22],
            ['Id_municipio' => 158, 'Municipio' => 'San Rafael Petzal', 'Id_departamento' => 22],
            ['Id_municipio' => 159, 'Municipio' => 'San Sebastián Coatán', 'Id_departamento' => 22],
            ['Id_municipio' => 160, 'Municipio' => 'San Sebastián Huehuetenango', 'Id_departamento' => 22],
            ['Id_municipio' => 161, 'Municipio' => 'Santa Ana Huista', 'Id_departamento' => 22],
            ['Id_municipio' => 162, 'Municipio' => 'Santa Bárbara', 'Id_departamento' => 22],
            ['Id_municipio' => 163, 'Municipio' => 'Santa Cruz Barillas', 'Id_departamento' => 22],
            ['Id_municipio' => 164, 'Municipio' => 'Santa Eulalia', 'Id_departamento' => 22],
            ['Id_municipio' => 165, 'Municipio' => 'Santiago Chimaltenango', 'Id_departamento' => 22],
            ['Id_municipio' => 166, 'Municipio' => 'Tectitán', 'Id_departamento' => 22],
            ['Id_municipio' => 167, 'Municipio' => 'Todos Santos Cuchumatán', 'Id_departamento' => 22],
            ['Id_municipio' => 168, 'Municipio' => 'Unión Cantinil', 'Id_departamento' => 22],
            ['Id_municipio' => 169, 'Municipio' => 'Puerto Barrios', 'Id_departamento' => 23],
            ['Id_municipio' => 170, 'Municipio' => 'El Estor', 'Id_departamento' => 23],
            ['Id_municipio' => 171, 'Municipio' => 'Livingston', 'Id_departamento' => 23],
            ['Id_municipio' => 172, 'Municipio' => 'Los Amates', 'Id_departamento' => 23],
            ['Id_municipio' => 173, 'Municipio' => 'Morales', 'Id_departamento' => 23],
            ['Id_municipio' => 174, 'Municipio' => 'Jalapa', 'Id_departamento' => 24],
            ['Id_municipio' => 175, 'Municipio' => 'Mataquescuintla', 'Id_departamento' => 24],
            ['Id_municipio' => 176, 'Municipio' => 'Monjas', 'Id_departamento' => 24],
            ['Id_municipio' => 177, 'Municipio' => 'San Carlos Alzatate', 'Id_departamento' => 24],
            ['Id_municipio' => 178, 'Municipio' => 'San Luis Jilotepeque', 'Id_departamento' => 24],
            ['Id_municipio' => 179, 'Municipio' => 'San Manuel Chaparrón', 'Id_departamento' => 24],
            ['Id_municipio' => 180, 'Municipio' => 'San Pedro Pinula', 'Id_departamento' => 24],
            ['Id_municipio' => 181, 'Municipio' => 'Jutiapa', 'Id_departamento' => 25],
            ['Id_municipio' => 182, 'Municipio' => 'Agua Blanca', 'Id_departamento' => 25],
            ['Id_municipio' => 183, 'Municipio' => 'Asunción Mita', 'Id_departamento' => 25],
            ['Id_municipio' => 184, 'Municipio' => 'Atescatempa', 'Id_departamento' => 25],
            ['Id_municipio' => 185, 'Municipio' => 'Comapa', 'Id_departamento' => 25],
            ['Id_municipio' => 186, 'Municipio' => 'Conguaco', 'Id_departamento' => 25],
            ['Id_municipio' => 187, 'Municipio' => 'El Adelanto', 'Id_departamento' => 25],
            ['Id_municipio' => 188, 'Municipio' => 'El Progreso', 'Id_departamento' => 25],
            ['Id_municipio' => 189, 'Municipio' => 'Jalpatagua', 'Id_departamento' => 25],
            ['Id_municipio' => 190, 'Municipio' => 'Jerez', 'Id_departamento' => 25],
            ['Id_municipio' => 191, 'Municipio' => 'Moyuta', 'Id_departamento' => 25],
            ['Id_municipio' => 192, 'Municipio' => 'Pasaco', 'Id_departamento' => 25],
            ['Id_municipio' => 193, 'Municipio' => 'Quesada', 'Id_departamento' => 25],
            ['Id_municipio' => 194, 'Municipio' => 'San José Acatempa', 'Id_departamento' => 25],
            ['Id_municipio' => 195, 'Municipio' => 'Santa Catarina Mita', 'Id_departamento' => 25],
            ['Id_municipio' => 196, 'Municipio' => 'Yupiltepeque', 'Id_departamento' => 25],
            ['Id_municipio' => 197, 'Municipio' => 'Zapotitlán', 'Id_departamento' => 25],
            ['Id_municipio' => 198, 'Municipio' => 'Flores', 'Id_departamento' => 26],
            ['Id_municipio' => 199, 'Municipio' => 'Dolores', 'Id_departamento' => 26],
            ['Id_municipio' => 200, 'Municipio' => 'El Chal', 'Id_departamento' => 26],
            ['Id_municipio' => 201, 'Municipio' => 'La Libertad', 'Id_departamento' => 26],
            ['Id_municipio' => 202, 'Municipio' => 'Las Cruces', 'Id_departamento' => 26],
            ['Id_municipio' => 203, 'Municipio' => 'Melchor de Mencos', 'Id_departamento' => 26],
            ['Id_municipio' => 204, 'Municipio' => 'Poptún', 'Id_departamento' => 26],
            ['Id_municipio' => 205, 'Municipio' => 'San Andrés', 'Id_departamento' => 26],
            ['Id_municipio' => 206, 'Municipio' => 'San Benito', 'Id_departamento' => 26],
            ['Id_municipio' => 207, 'Municipio' => 'San Francisco', 'Id_departamento' => 26],
            ['Id_municipio' => 208, 'Municipio' => 'San José', 'Id_departamento' => 26],
            ['Id_municipio' => 209, 'Municipio' => 'San Luis', 'Id_departamento' => 26],
            ['Id_municipio' => 210, 'Municipio' => 'Santa Ana', 'Id_departamento' => 26],
            ['Id_municipio' => 211, 'Municipio' => 'Sayaxché', 'Id_departamento' => 26],
            ['Id_municipio' => 212, 'Municipio' => 'Quetzaltenango', 'Id_departamento' => 27],
            ['Id_municipio' => 213, 'Municipio' => 'Almolonga', 'Id_departamento' => 27],
            ['Id_municipio' => 214, 'Municipio' => 'Cabricán', 'Id_departamento' => 27],
            ['Id_municipio' => 215, 'Municipio' => 'Cajolá', 'Id_departamento' => 27],
            ['Id_municipio' => 216, 'Municipio' => 'Cantel', 'Id_departamento' => 27],
            ['Id_municipio' => 217, 'Municipio' => 'Coatepeque', 'Id_departamento' => 27],
            ['Id_municipio' => 218, 'Municipio' => 'Colomba Costa Cuca', 'Id_departamento' => 27],
            ['Id_municipio' => 219, 'Municipio' => 'Concepción Chiquirichapa', 'Id_departamento' => 27],
            ['Id_municipio' => 220, 'Municipio' => 'El Palmar', 'Id_departamento' => 27],
            ['Id_municipio' => 221, 'Municipio' => 'Flores Costa Cuca', 'Id_departamento' => 27],
            ['Id_municipio' => 222, 'Municipio' => 'Génova', 'Id_departamento' => 27],
            ['Id_municipio' => 223, 'Municipio' => 'Huitán', 'Id_departamento' => 27],
            ['Id_municipio' => 224, 'Municipio' => 'La Esperanza', 'Id_departamento' => 27],
            ['Id_municipio' => 225, 'Municipio' => 'Olintepeque', 'Id_departamento' => 27],
            ['Id_municipio' => 226, 'Municipio' => 'Palestina de Los Altos', 'Id_departamento' => 27],
            ['Id_municipio' => 227, 'Municipio' => 'Salcajá', 'Id_departamento' => 27],
            ['Id_municipio' => 228, 'Municipio' => 'San Carlos Sija', 'Id_departamento' => 27],
            ['Id_municipio' => 229, 'Municipio' => 'San Francisco La Unión', 'Id_departamento' => 27],
            ['Id_municipio' => 230, 'Municipio' => 'San Juan Ostuncalco', 'Id_departamento' => 27],
            ['Id_municipio' => 231, 'Municipio' => 'San Martín Sacatepéquez', 'Id_departamento' => 27],
            ['Id_municipio' => 232, 'Municipio' => 'San Mateo', 'Id_departamento' => 27],
            ['Id_municipio' => 233, 'Municipio' => 'San Miguel Sigüilá', 'Id_departamento' => 27],
            ['Id_municipio' => 234, 'Municipio' => 'Sibilia', 'Id_departamento' => 27],
            ['Id_municipio' => 235, 'Municipio' => 'Zunil', 'Id_departamento' => 27],
            ['Id_municipio' => 236, 'Municipio' => 'Santa Cruz del Quiché', 'Id_departamento' => 28],
            ['Id_municipio' => 237, 'Municipio' => 'Canillá', 'Id_departamento' => 28],
            ['Id_municipio' => 238, 'Municipio' => 'Chajul', 'Id_departamento' => 28],
            ['Id_municipio' => 239, 'Municipio' => 'Chicamán', 'Id_departamento' => 28],
            ['Id_municipio' => 240, 'Municipio' => 'Chiché', 'Id_departamento' => 28],
            ['Id_municipio' => 241, 'Municipio' => 'Chichicastenango', 'Id_departamento' => 28],
            ['Id_municipio' => 242, 'Municipio' => 'Chinique', 'Id_departamento' => 28],
            ['Id_municipio' => 243, 'Municipio' => 'Cunén', 'Id_departamento' => 28],
            ['Id_municipio' => 244, 'Municipio' => 'Ixcán', 'Id_departamento' => 28],
            ['Id_municipio' => 245, 'Municipio' => 'Joyabaj', 'Id_departamento' => 28],
            ['Id_municipio' => 246, 'Municipio' => 'Nebaj', 'Id_departamento' => 28],
            ['Id_municipio' => 247, 'Municipio' => 'Pachalum', 'Id_departamento' => 28],
            ['Id_municipio' => 248, 'Municipio' => 'Patzité', 'Id_departamento' => 28],
            ['Id_municipio' => 249, 'Municipio' => 'Sacapulas', 'Id_departamento' => 28],
            ['Id_municipio' => 250, 'Municipio' => 'San Andrés Sajcabajá', 'Id_departamento' => 28],
            ['Id_municipio' => 251, 'Municipio' => 'San Antonio Ilotenango', 'Id_departamento' => 28],
            ['Id_municipio' => 252, 'Municipio' => 'San Bartolomé Jocotenango', 'Id_departamento' => 28],
            ['Id_municipio' => 253, 'Municipio' => 'San Juan Cotzal', 'Id_departamento' => 28],
            ['Id_municipio' => 254, 'Municipio' => 'San Pedro Jocopilas', 'Id_departamento' => 28],
            ['Id_municipio' => 255, 'Municipio' => 'Uspantán', 'Id_departamento' => 28],
            ['Id_municipio' => 256, 'Municipio' => 'Zacualpa', 'Id_departamento' => 28],
            ['Id_municipio' => 257, 'Municipio' => 'Retalhuleu', 'Id_departamento' => 29],
            ['Id_municipio' => 258, 'Municipio' => 'Champerico', 'Id_departamento' => 29],
            ['Id_municipio' => 259, 'Municipio' => 'El Asintal', 'Id_departamento' => 29],
            ['Id_municipio' => 260, 'Municipio' => 'Nuevo San Carlos', 'Id_departamento' => 29],
            ['Id_municipio' => 261, 'Municipio' => 'San Andrés Villa Seca', 'Id_departamento' => 29],
            ['Id_municipio' => 262, 'Municipio' => 'San Felipe', 'Id_departamento' => 29],
            ['Id_municipio' => 263, 'Municipio' => 'San Martín Zapotitlán', 'Id_departamento' => 29],
            ['Id_municipio' => 264, 'Municipio' => 'San Sebastián', 'Id_departamento' => 29],
            ['Id_municipio' => 265, 'Municipio' => 'Santa Cruz Muluá', 'Id_departamento' => 29],
            ['Id_municipio' => 266, 'Municipio' => 'Antigua Guatemala', 'Id_departamento' => 30],
            ['Id_municipio' => 267, 'Municipio' => 'Alotenango', 'Id_departamento' => 30],
            ['Id_municipio' => 268, 'Municipio' => 'Ciudad Vieja', 'Id_departamento' => 30],
            ['Id_municipio' => 269, 'Municipio' => 'Jocotenango', 'Id_departamento' => 30],
            ['Id_municipio' => 270, 'Municipio' => 'Magdalena Milpas Altas', 'Id_departamento' => 30],
            ['Id_municipio' => 271, 'Municipio' => 'Pastores', 'Id_departamento' => 30],
            ['Id_municipio' => 272, 'Municipio' => 'San Antonio Aguas Calientes', 'Id_departamento' => 30],
            ['Id_municipio' => 273, 'Municipio' => 'San Bartolomé Milpas Altas', 'Id_departamento' => 30],
            ['Id_municipio' => 274, 'Municipio' => 'San Lucas Sacatepéquez', 'Id_departamento' => 30],
            ['Id_municipio' => 275, 'Municipio' => 'San Miguel Dueñas', 'Id_departamento' => 30],
            ['Id_municipio' => 276, 'Municipio' => 'Santa Catarina Barahona', 'Id_departamento' => 30],
            ['Id_municipio' => 277, 'Municipio' => 'Santa Lucía Milpas Altas', 'Id_departamento' => 30],
            ['Id_municipio' => 278, 'Municipio' => 'Santa María de Jesús', 'Id_departamento' => 30],
            ['Id_municipio' => 279, 'Municipio' => 'Santiago Sacatepéquez', 'Id_departamento' => 30],
            ['Id_municipio' => 280, 'Municipio' => 'Santo Domingo Xenacoj', 'Id_departamento' => 30],
            ['Id_municipio' => 281, 'Municipio' => 'Sumpango', 'Id_departamento' => 30],
            ['Id_municipio' => 282, 'Municipio' => 'San Marcos', 'Id_departamento' => 31],
            ['Id_municipio' => 283, 'Municipio' => 'Ayutla', 'Id_departamento' => 31],
            ['Id_municipio' => 284, 'Municipio' => 'Catarina', 'Id_departamento' => 31],
            ['Id_municipio' => 285, 'Municipio' => 'Comitancillo', 'Id_departamento' => 31],
            ['Id_municipio' => 286, 'Municipio' => 'Concepción Tutuapa', 'Id_departamento' => 31],
            ['Id_municipio' => 287, 'Municipio' => 'El Quetzal', 'Id_departamento' => 31],
            ['Id_municipio' => 288, 'Municipio' => 'El Tumbador', 'Id_departamento' => 31],
            ['Id_municipio' => 289, 'Municipio' => 'Esquipulas Palo Gordo', 'Id_departamento' => 31],
            ['Id_municipio' => 290, 'Municipio' => 'Ixchiguán', 'Id_departamento' => 31],
            ['Id_municipio' => 291, 'Municipio' => 'La Blanca', 'Id_departamento' => 31],
            ['Id_municipio' => 292, 'Municipio' => 'La Reforma', 'Id_departamento' => 31],
            ['Id_municipio' => 293, 'Municipio' => 'Malacatán', 'Id_departamento' => 31],
            ['Id_municipio' => 294, 'Municipio' => 'Nuevo Progreso', 'Id_departamento' => 31],
            ['Id_municipio' => 295, 'Municipio' => 'Ocós', 'Id_departamento' => 31],
            ['Id_municipio' => 296, 'Municipio' => 'Pajapita', 'Id_departamento' => 31],
            ['Id_municipio' => 297, 'Municipio' => 'Río Blanco', 'Id_departamento' => 31],
            ['Id_municipio' => 298, 'Municipio' => 'San Antonio Sacatepéquez', 'Id_departamento' => 31],
            ['Id_municipio' => 299, 'Municipio' => 'San Cristóbal Cucho', 'Id_departamento' => 31],
            ['Id_municipio' => 300, 'Municipio' => 'San José El Rodeo', 'Id_departamento' => 31],
            ['Id_municipio' => 301, 'Municipio' => 'San José Ojetenam', 'Id_departamento' => 31],
            ['Id_municipio' => 302, 'Municipio' => 'San Lorenzo', 'Id_departamento' => 31],
            ['Id_municipio' => 303, 'Municipio' => 'San Miguel Ixtahuacán', 'Id_departamento' => 31],
            ['Id_municipio' => 304, 'Municipio' => 'San Pablo', 'Id_departamento' => 31],
            ['Id_municipio' => 305, 'Municipio' => 'San Pedro Sacatepéquez', 'Id_departamento' => 31],
            ['Id_municipio' => 306, 'Municipio' => 'San Rafael Pie de la Cuesta', 'Id_departamento' => 31],
            ['Id_municipio' => 307, 'Municipio' => 'Sibinal', 'Id_departamento' => 31],
            ['Id_municipio' => 308, 'Municipio' => 'Sipacapa', 'Id_departamento' => 31],
            ['Id_municipio' => 309, 'Municipio' => 'Tacaná', 'Id_departamento' => 31],
            ['Id_municipio' => 310, 'Municipio' => 'Tajumulco', 'Id_departamento' => 31],
            ['Id_municipio' => 311, 'Municipio' => 'Tejutla', 'Id_departamento' => 31],
            ['Id_municipio' => 312, 'Municipio' => 'Cuilapa', 'Id_departamento' => 32],
            ['Id_municipio' => 313, 'Municipio' => 'Barberena', 'Id_departamento' => 32],
            ['Id_municipio' => 314, 'Municipio' => 'Casillas', 'Id_departamento' => 32],
            ['Id_municipio' => 315, 'Municipio' => 'Chiquimulilla', 'Id_departamento' => 32],
            ['Id_municipio' => 316, 'Municipio' => 'Guazacapán', 'Id_departamento' => 32],
            ['Id_municipio' => 317, 'Municipio' => 'Nueva Santa Rosa', 'Id_departamento' => 32],
            ['Id_municipio' => 318, 'Municipio' => 'Oratorio', 'Id_departamento' => 32],
            ['Id_municipio' => 319, 'Municipio' => 'Pueblo Nuevo Viñas', 'Id_departamento' => 32],
            ['Id_municipio' => 320, 'Municipio' => 'San Juan Tecuaco', 'Id_departamento' => 32],
            ['Id_municipio' => 321, 'Municipio' => 'San Rafael Las Flores', 'Id_departamento' => 32],
            ['Id_municipio' => 322, 'Municipio' => 'Santa Cruz Naranjo', 'Id_departamento' => 32],
            ['Id_municipio' => 323, 'Municipio' => 'Santa María Ixhuatán', 'Id_departamento' => 32],
            ['Id_municipio' => 324, 'Municipio' => 'Santa Rosa de Lima', 'Id_departamento' => 32],
            ['Id_municipio' => 325, 'Municipio' => 'Taxisco', 'Id_departamento' => 32],
            ['Id_municipio' => 326, 'Municipio' => 'Sololá', 'Id_departamento' => 33],
            ['Id_municipio' => 327, 'Municipio' => 'Concepción', 'Id_departamento' => 33],
            ['Id_municipio' => 328, 'Municipio' => 'Nahualá', 'Id_departamento' => 33],
            ['Id_municipio' => 329, 'Municipio' => 'Panajachel', 'Id_departamento' => 33],
            ['Id_municipio' => 330, 'Municipio' => 'San Andrés Semetabaj', 'Id_departamento' => 33],
            ['Id_municipio' => 331, 'Municipio' => 'San Antonio Palopó', 'Id_departamento' => 33],
            ['Id_municipio' => 332, 'Municipio' => 'San José Chacayá', 'Id_departamento' => 33],
            ['Id_municipio' => 333, 'Municipio' => 'San Juan La Laguna', 'Id_departamento' => 33],
            ['Id_municipio' => 334, 'Municipio' => 'San Lucas Tolimán', 'Id_departamento' => 33],
            ['Id_municipio' => 335, 'Municipio' => 'San Marcos La Laguna', 'Id_departamento' => 33],
            ['Id_municipio' => 336, 'Municipio' => 'San Pablo La Laguna', 'Id_departamento' => 33],
            ['Id_municipio' => 337, 'Municipio' => 'San Pedro La Laguna', 'Id_departamento' => 33],
            ['Id_municipio' => 338, 'Municipio' => 'Santa Catarina Ixtahuacán', 'Id_departamento' => 33],
            ['Id_municipio' => 339, 'Municipio' => 'Santa Catarina Palopó', 'Id_departamento' => 33],
            ['Id_municipio' => 340, 'Municipio' => 'Santa Clara La Laguna', 'Id_departamento' => 33],
            ['Id_municipio' => 341, 'Municipio' => 'Santa Cruz La Laguna', 'Id_departamento' => 33],
            ['Id_municipio' => 342, 'Municipio' => 'Santa Lucía Utatlán', 'Id_departamento' => 33],
            ['Id_municipio' => 343, 'Municipio' => 'Santa María Visitación', 'Id_departamento' => 33],
            ['Id_municipio' => 344, 'Municipio' => 'Santiago Atitlán', 'Id_departamento' => 33],
            ['Id_municipio' => 345, 'Municipio' => 'Mazatenango', 'Id_departamento' => 34],
            ['Id_municipio' => 346, 'Municipio' => 'Chicacao', 'Id_departamento' => 34],
            ['Id_municipio' => 347, 'Municipio' => 'Cuyotenango', 'Id_departamento' => 34],
            ['Id_municipio' => 348, 'Municipio' => 'Patulul', 'Id_departamento' => 34],
            ['Id_municipio' => 349, 'Municipio' => 'Pueblo Nuevo', 'Id_departamento' => 34],
            ['Id_municipio' => 350, 'Municipio' => 'Río Bravo', 'Id_departamento' => 34],
            ['Id_municipio' => 351, 'Municipio' => 'Samayac', 'Id_departamento' => 34],
            ['Id_municipio' => 352, 'Municipio' => 'San Antonio Suchitepéquez', 'Id_departamento' => 34],
            ['Id_municipio' => 353, 'Municipio' => 'San Bernardino', 'Id_departamento' => 34],
            ['Id_municipio' => 354, 'Municipio' => 'San Francisco Zapotitlán', 'Id_departamento' => 34],
            ['Id_municipio' => 355, 'Municipio' => 'San Gabriel', 'Id_departamento' => 34],
            ['Id_municipio' => 356, 'Municipio' => 'San José El Ídolo', 'Id_departamento' => 34],
            ['Id_municipio' => 357, 'Municipio' => 'San José La Máquina', 'Id_departamento' => 34],
            ['Id_municipio' => 358, 'Municipio' => 'San Juan Bautista', 'Id_departamento' => 34],
            ['Id_municipio' => 359, 'Municipio' => 'San Lorenzo', 'Id_departamento' => 34],
            ['Id_municipio' => 360, 'Municipio' => 'San Miguel Panán', 'Id_departamento' => 34],
            ['Id_municipio' => 361, 'Municipio' => 'San Pablo Jocopilas', 'Id_departamento' => 34],
            ['Id_municipio' => 362, 'Municipio' => 'Santa Bárbara', 'Id_departamento' => 34],
            ['Id_municipio' => 363, 'Municipio' => 'Santo Domingo Suchitepéquez', 'Id_departamento' => 34],
            ['Id_municipio' => 364, 'Municipio' => 'Santo Tomás La Unión', 'Id_departamento' => 34],
            ['Id_municipio' => 365, 'Municipio' => 'Zunilito', 'Id_departamento' => 34],
            ['Id_municipio' => 366, 'Municipio' => 'Totonicapán', 'Id_departamento' => 35],
            ['Id_municipio' => 367, 'Municipio' => 'Momostenango', 'Id_departamento' => 35],
            ['Id_municipio' => 368, 'Municipio' => 'San Andrés Xecul', 'Id_departamento' => 35],
            ['Id_municipio' => 369, 'Municipio' => 'San Bartolo Aguas Calientes', 'Id_departamento' => 35],
            ['Id_municipio' => 370, 'Municipio' => 'San Cristóbal Totonicapán', 'Id_departamento' => 35],
            ['Id_municipio' => 371, 'Municipio' => 'San Francisco El Alto', 'Id_departamento' => 35],
            ['Id_municipio' => 372, 'Municipio' => 'Santa Lucía La Reforma', 'Id_departamento' => 35],
            ['Id_municipio' => 373, 'Municipio' => 'Santa María Chiquimula', 'Id_departamento' => 35],
            ['Id_municipio' => 374, 'Municipio' => 'Zacapa', 'Id_departamento' => 36],
            ['Id_municipio' => 375, 'Municipio' => 'Cabañas', 'Id_departamento' => 36],
            ['Id_municipio' => 376, 'Municipio' => 'Estanzuela', 'Id_departamento' => 36],
            ['Id_municipio' => 377, 'Municipio' => 'Gualán', 'Id_departamento' => 36],
            ['Id_municipio' => 378, 'Municipio' => 'Huité', 'Id_departamento' => 36],
            ['Id_municipio' => 379, 'Municipio' => 'La Unión', 'Id_departamento' => 36],
            ['Id_municipio' => 380, 'Municipio' => 'Río Hondo', 'Id_departamento' => 36],
            ['Id_municipio' => 381, 'Municipio' => 'San Diego', 'Id_departamento' => 36],
            ['Id_municipio' => 382, 'Municipio' => 'San Jorge', 'Id_departamento' => 36],
            ['Id_municipio' => 383, 'Municipio' => 'Teculután', 'Id_departamento' => 36],
            ['Id_municipio' => 384, 'Municipio' => 'Usumatlán', 'Id_departamento' => 36],
            // Honduras
            ['Id_municipio' => 13, 'Municipio' => 'Distrito Central', 'Id_departamento' => 9],
            ['Id_municipio' => 14, 'Municipio' => 'San Pedro Sula', 'Id_departamento' => 10],
            ['Id_municipio' => 15, 'Municipio' => 'Choloma', 'Id_departamento' => 10],
            ['Id_municipio' => 16, 'Municipio' => 'La Ceiba', 'Id_departamento' => 11],
        ];
    }

    /**
     * Canonical Central American districts catalog.
     * Note: Guatemala (Id_pais = 2, requiere_distrito = 0) intentionally has no districts.
     *
     * @return list<array{Id_distrito: int, Distrito: string, Id_municipio: int}>
     */
    public static function getDistritos(): array
    {
        return [
            // El Salvador
            ['Id_distrito' => 1, 'Distrito' => 'Ahuachapán', 'Id_municipio' => 2],
            ['Id_distrito' => 2, 'Distrito' => 'Apaneca', 'Id_municipio' => 2],
            ['Id_distrito' => 3, 'Distrito' => 'Atiquizaya', 'Id_municipio' => 1],
            ['Id_distrito' => 4, 'Distrito' => 'Concepción de Ataco', 'Id_municipio' => 2],
            ['Id_distrito' => 5, 'Distrito' => 'El Refugio', 'Id_municipio' => 1],
            ['Id_distrito' => 6, 'Distrito' => 'Guaymango', 'Id_municipio' => 3],
            ['Id_distrito' => 7, 'Distrito' => 'Jujutla', 'Id_municipio' => 3],
            ['Id_distrito' => 8, 'Distrito' => 'San Francisco Menéndez', 'Id_municipio' => 3],
            ['Id_distrito' => 9, 'Distrito' => 'San Lorenzo', 'Id_municipio' => 1],
            ['Id_distrito' => 10, 'Distrito' => 'San Pedro Puxtla', 'Id_municipio' => 3],
            ['Id_distrito' => 11, 'Distrito' => 'Tacuba', 'Id_municipio' => 2],
            ['Id_distrito' => 12, 'Distrito' => 'Turín', 'Id_municipio' => 1],
            ['Id_distrito' => 13, 'Distrito' => 'Candelaria de la Frontera', 'Id_municipio' => 7],
            ['Id_distrito' => 14, 'Distrito' => 'Chalchuapa', 'Id_municipio' => 7],
            ['Id_distrito' => 15, 'Distrito' => 'Coatepeque', 'Id_municipio' => 6],
            ['Id_distrito' => 16, 'Distrito' => 'El Congo', 'Id_municipio' => 6],
            ['Id_distrito' => 17, 'Distrito' => 'El Porvenir', 'Id_municipio' => 7],
            ['Id_distrito' => 18, 'Distrito' => 'Masahuat', 'Id_municipio' => 4],
            ['Id_distrito' => 19, 'Distrito' => 'Metapán', 'Id_municipio' => 4],
            ['Id_distrito' => 20, 'Distrito' => 'San Antonio Pajonal', 'Id_municipio' => 7],
            ['Id_distrito' => 21, 'Distrito' => 'San Sebastián Salitrillo', 'Id_municipio' => 7],
            ['Id_distrito' => 22, 'Distrito' => 'Santa Ana', 'Id_municipio' => 5],
            ['Id_distrito' => 23, 'Distrito' => 'Santa Rosa Guachipilín', 'Id_municipio' => 4],
            ['Id_distrito' => 24, 'Distrito' => 'Santiago de la Frontera', 'Id_municipio' => 7],
            ['Id_distrito' => 25, 'Distrito' => 'Texistepeque', 'Id_municipio' => 4],
            ['Id_distrito' => 26, 'Distrito' => 'Acajutla', 'Id_municipio' => 11],
            ['Id_distrito' => 27, 'Distrito' => 'Armenia', 'Id_municipio' => 10],
            ['Id_distrito' => 28, 'Distrito' => 'Caluco', 'Id_municipio' => 10],
            ['Id_distrito' => 29, 'Distrito' => 'Cuisnahuat', 'Id_municipio' => 10],
            ['Id_distrito' => 30, 'Distrito' => 'Izalco', 'Id_municipio' => 10],
            ['Id_distrito' => 31, 'Distrito' => 'Juayúa', 'Id_municipio' => 8],
            ['Id_distrito' => 32, 'Distrito' => 'Nahuizalco', 'Id_municipio' => 8],
            ['Id_distrito' => 33, 'Distrito' => 'Nahulingo', 'Id_municipio' => 9],
            ['Id_distrito' => 34, 'Distrito' => 'Salcoatitán', 'Id_municipio' => 8],
            ['Id_distrito' => 35, 'Distrito' => 'San Antonio del Monte', 'Id_municipio' => 9],
            ['Id_distrito' => 36, 'Distrito' => 'San Julián', 'Id_municipio' => 10],
            ['Id_distrito' => 37, 'Distrito' => 'Santa Catarina Masahuat', 'Id_municipio' => 8],
            ['Id_distrito' => 38, 'Distrito' => 'Santa Isabel Ishuatán', 'Id_municipio' => 10],
            ['Id_distrito' => 39, 'Distrito' => 'Santo Domingo de Guzmán', 'Id_municipio' => 9],
            ['Id_distrito' => 40, 'Distrito' => 'Sonsonate', 'Id_municipio' => 9],
            ['Id_distrito' => 41, 'Distrito' => 'Sonzacate', 'Id_municipio' => 9],
            ['Id_distrito' => 42, 'Distrito' => 'Agua Caliente', 'Id_municipio' => 13],
            ['Id_distrito' => 43, 'Distrito' => 'Arcatao', 'Id_municipio' => 14],
            ['Id_distrito' => 44, 'Distrito' => 'Azacualpa', 'Id_municipio' => 14],
            ['Id_distrito' => 45, 'Distrito' => 'Chalatenango', 'Id_municipio' => 14],
            ['Id_distrito' => 46, 'Distrito' => 'Citalá', 'Id_municipio' => 12],
            ['Id_distrito' => 47, 'Distrito' => 'Comalapa', 'Id_municipio' => 14],
            ['Id_distrito' => 48, 'Distrito' => 'Concepción Quezaltepeque', 'Id_municipio' => 14],
            ['Id_distrito' => 49, 'Distrito' => 'Dulce Nombre de María', 'Id_municipio' => 13],
            ['Id_distrito' => 50, 'Distrito' => 'El Carrizal', 'Id_municipio' => 14],
            ['Id_distrito' => 51, 'Distrito' => 'El Paraíso', 'Id_municipio' => 13],
            ['Id_distrito' => 52, 'Distrito' => 'La Laguna', 'Id_municipio' => 14],
            ['Id_distrito' => 53, 'Distrito' => 'La Palma', 'Id_municipio' => 12],
            ['Id_distrito' => 54, 'Distrito' => 'La Reina', 'Id_municipio' => 13],
            ['Id_distrito' => 55, 'Distrito' => 'Las Vueltas', 'Id_municipio' => 14],
            ['Id_distrito' => 56, 'Distrito' => 'Nombre de Jesús', 'Id_municipio' => 14],
            ['Id_distrito' => 57, 'Distrito' => 'Nueva Concepción', 'Id_municipio' => 13],
            ['Id_distrito' => 58, 'Distrito' => 'Nueva Trinidad', 'Id_municipio' => 14],
            ['Id_distrito' => 59, 'Distrito' => 'Ojos de Agua', 'Id_municipio' => 14],
            ['Id_distrito' => 60, 'Distrito' => 'Potonico', 'Id_municipio' => 14],
            ['Id_distrito' => 61, 'Distrito' => 'San Antonio de la Cruz', 'Id_municipio' => 14],
            ['Id_distrito' => 62, 'Distrito' => 'San Antonio Los Ranchos', 'Id_municipio' => 14],
            ['Id_distrito' => 63, 'Distrito' => 'San Fernando', 'Id_municipio' => 13],
            ['Id_distrito' => 64, 'Distrito' => 'San Francisco Lempa', 'Id_municipio' => 14],
            ['Id_distrito' => 65, 'Distrito' => 'San Francisco Morazán', 'Id_municipio' => 13],
            ['Id_distrito' => 66, 'Distrito' => 'San Ignacio', 'Id_municipio' => 12],
            ['Id_distrito' => 67, 'Distrito' => 'San Isidro Labrador', 'Id_municipio' => 14],
            ['Id_distrito' => 68, 'Distrito' => 'Cancasque', 'Id_municipio' => 14],
            ['Id_distrito' => 69, 'Distrito' => 'Las Flores', 'Id_municipio' => 14],
            ['Id_distrito' => 70, 'Distrito' => 'San Luis del Carmen', 'Id_municipio' => 14],
            ['Id_distrito' => 71, 'Distrito' => 'San Miguel de Mercedes', 'Id_municipio' => 14],
            ['Id_distrito' => 72, 'Distrito' => 'San Rafael', 'Id_municipio' => 13],
            ['Id_distrito' => 73, 'Distrito' => 'Santa Rita', 'Id_municipio' => 13],
            ['Id_distrito' => 74, 'Distrito' => 'Tejutla', 'Id_municipio' => 13],
            ['Id_distrito' => 75, 'Distrito' => 'Antiguo Cuscatlán', 'Id_municipio' => 18],
            ['Id_distrito' => 76, 'Distrito' => 'Chiltiupán', 'Id_municipio' => 19],
            ['Id_distrito' => 77, 'Distrito' => 'Ciudad Arce', 'Id_municipio' => 16],
            ['Id_distrito' => 78, 'Distrito' => 'Colón', 'Id_municipio' => 17],
            ['Id_distrito' => 79, 'Distrito' => 'Comasagua', 'Id_municipio' => 20],
            ['Id_distrito' => 80, 'Distrito' => 'Huizúcar', 'Id_municipio' => 18],
            ['Id_distrito' => 81, 'Distrito' => 'Jayaque', 'Id_municipio' => 17],
            ['Id_distrito' => 82, 'Distrito' => 'Jicalapa', 'Id_municipio' => 19],
            ['Id_distrito' => 83, 'Distrito' => 'La Libertad', 'Id_municipio' => 19],
            ['Id_distrito' => 84, 'Distrito' => 'Nuevo Cuscatlán', 'Id_municipio' => 18],
            ['Id_distrito' => 85, 'Distrito' => 'Quezaltepeque', 'Id_municipio' => 15],
            ['Id_distrito' => 86, 'Distrito' => 'Sacacoyo', 'Id_municipio' => 17],
            ['Id_distrito' => 87, 'Distrito' => 'San José Villanueva', 'Id_municipio' => 18],
            ['Id_distrito' => 88, 'Distrito' => 'San Juan Opico', 'Id_municipio' => 16],
            ['Id_distrito' => 89, 'Distrito' => 'San Matías', 'Id_municipio' => 15],
            ['Id_distrito' => 90, 'Distrito' => 'San Pablo Tacachico', 'Id_municipio' => 16],
            ['Id_distrito' => 91, 'Distrito' => 'Santa Tecla', 'Id_municipio' => 20],
            ['Id_distrito' => 92, 'Distrito' => 'Talnique', 'Id_municipio' => 17],
            ['Id_distrito' => 93, 'Distrito' => 'Tamanique', 'Id_municipio' => 19],
            ['Id_distrito' => 94, 'Distrito' => 'Teotepeque', 'Id_municipio' => 19],
            ['Id_distrito' => 95, 'Distrito' => 'Tepecoyo', 'Id_municipio' => 17],
            ['Id_distrito' => 96, 'Distrito' => 'Zaragoza', 'Id_municipio' => 18],
            ['Id_distrito' => 97, 'Distrito' => 'Aguilares', 'Id_municipio' => 21],
            ['Id_distrito' => 98, 'Distrito' => 'Apopa', 'Id_municipio' => 22],
            ['Id_distrito' => 99, 'Distrito' => 'Ayutuxtepeque', 'Id_municipio' => 24],
            ['Id_distrito' => 100, 'Distrito' => 'Cuscatancingo', 'Id_municipio' => 24],
            ['Id_distrito' => 101, 'Distrito' => 'Ciudad Delgado', 'Id_municipio' => 24],
            ['Id_distrito' => 102, 'Distrito' => 'El Paisnal', 'Id_municipio' => 21],
            ['Id_distrito' => 103, 'Distrito' => 'Guazapa', 'Id_municipio' => 21],
            ['Id_distrito' => 104, 'Distrito' => 'Ilopango', 'Id_municipio' => 23],
            ['Id_distrito' => 105, 'Distrito' => 'Mejicanos', 'Id_municipio' => 24],
            ['Id_distrito' => 106, 'Distrito' => 'Nejapa', 'Id_municipio' => 22],
            ['Id_distrito' => 107, 'Distrito' => 'Panchimalco', 'Id_municipio' => 25],
            ['Id_distrito' => 108, 'Distrito' => 'Rosario de Mora', 'Id_municipio' => 25],
            ['Id_distrito' => 109, 'Distrito' => 'San Marcos', 'Id_municipio' => 25],
            ['Id_distrito' => 110, 'Distrito' => 'San Martín', 'Id_municipio' => 23],
            ['Id_distrito' => 111, 'Distrito' => 'San Salvador', 'Id_municipio' => 24],
            ['Id_distrito' => 112, 'Distrito' => 'Santiago Texacuangos', 'Id_municipio' => 25],
            ['Id_distrito' => 113, 'Distrito' => 'Santo Tomás', 'Id_municipio' => 25],
            ['Id_distrito' => 114, 'Distrito' => 'Soyapango', 'Id_municipio' => 23],
            ['Id_distrito' => 115, 'Distrito' => 'Tonacatepeque', 'Id_municipio' => 23],
            ['Id_distrito' => 116, 'Distrito' => 'Candelaria', 'Id_municipio' => 27],
            ['Id_distrito' => 117, 'Distrito' => 'Cojutepeque', 'Id_municipio' => 27],
            ['Id_distrito' => 118, 'Distrito' => 'El Carmen', 'Id_municipio' => 27],
            ['Id_distrito' => 119, 'Distrito' => 'El Rosario', 'Id_municipio' => 27],
            ['Id_distrito' => 120, 'Distrito' => 'Monte San Juan', 'Id_municipio' => 27],
            ['Id_distrito' => 121, 'Distrito' => 'Oratorio de Concepción', 'Id_municipio' => 26],
            ['Id_distrito' => 122, 'Distrito' => 'San Bartolomé Perulapán', 'Id_municipio' => 26],
            ['Id_distrito' => 123, 'Distrito' => 'San Cristóbal', 'Id_municipio' => 27],
            ['Id_distrito' => 124, 'Distrito' => 'San José Guayabal', 'Id_municipio' => 26],
            ['Id_distrito' => 125, 'Distrito' => 'San Pedro Perulapán', 'Id_municipio' => 26],
            ['Id_distrito' => 126, 'Distrito' => 'San Rafael Cedros', 'Id_municipio' => 27],
            ['Id_distrito' => 127, 'Distrito' => 'San Ramón', 'Id_municipio' => 27],
            ['Id_distrito' => 128, 'Distrito' => 'Santa Cruz Analquito', 'Id_municipio' => 27],
            ['Id_distrito' => 129, 'Distrito' => 'Santa Cruz Michapa', 'Id_municipio' => 27],
            ['Id_distrito' => 130, 'Distrito' => 'Suchitoto', 'Id_municipio' => 26],
            ['Id_distrito' => 131, 'Distrito' => 'Tenancingo', 'Id_municipio' => 27],
            ['Id_distrito' => 132, 'Distrito' => 'Cuyultitán', 'Id_municipio' => 28],
            ['Id_distrito' => 133, 'Distrito' => 'El Rosario', 'Id_municipio' => 29],
            ['Id_distrito' => 134, 'Distrito' => 'Jerusalén', 'Id_municipio' => 29],
            ['Id_distrito' => 135, 'Distrito' => 'Mercedes La Ceiba', 'Id_municipio' => 29],
            ['Id_distrito' => 136, 'Distrito' => 'Olocuilta', 'Id_municipio' => 28],
            ['Id_distrito' => 137, 'Distrito' => 'Paraíso de Osorio', 'Id_municipio' => 28],
            ['Id_distrito' => 138, 'Distrito' => 'San Antonio Masahuat', 'Id_municipio' => 29],
            ['Id_distrito' => 139, 'Distrito' => 'San Emigdio', 'Id_municipio' => 28],
            ['Id_distrito' => 140, 'Distrito' => 'San Francisco Chinameca', 'Id_municipio' => 29],
            ['Id_distrito' => 141, 'Distrito' => 'San Juan Nonualco', 'Id_municipio' => 30],
            ['Id_distrito' => 142, 'Distrito' => 'San Juan Talpa', 'Id_municipio' => 29],
            ['Id_distrito' => 143, 'Distrito' => 'San Juan Tepezontes', 'Id_municipio' => 28],
            ['Id_distrito' => 144, 'Distrito' => 'San Luis La Herradura', 'Id_municipio' => 29],
            ['Id_distrito' => 145, 'Distrito' => 'San Luis Talpa', 'Id_municipio' => 29],
            ['Id_distrito' => 146, 'Distrito' => 'San Miguel Tepezontes', 'Id_municipio' => 28],
            ['Id_distrito' => 147, 'Distrito' => 'San Pedro Masahuat', 'Id_municipio' => 29],
            ['Id_distrito' => 148, 'Distrito' => 'San Pedro Nonualco', 'Id_municipio' => 29],
            ['Id_distrito' => 149, 'Distrito' => 'San Rafael Obrajuelo', 'Id_municipio' => 30],
            ['Id_distrito' => 150, 'Distrito' => 'Santa María Ostuma', 'Id_municipio' => 29],
            ['Id_distrito' => 151, 'Distrito' => 'Santiago Nonualco', 'Id_municipio' => 29],
            ['Id_distrito' => 152, 'Distrito' => 'Tapalhuaca', 'Id_municipio' => 28],
            ['Id_distrito' => 153, 'Distrito' => 'Zacatecoluca', 'Id_municipio' => 30],
            ['Id_distrito' => 154, 'Distrito' => 'Cinquera', 'Id_municipio' => 31],
            ['Id_distrito' => 155, 'Distrito' => 'Dolores', 'Id_municipio' => 32],
            ['Id_distrito' => 156, 'Distrito' => 'Guacotecti', 'Id_municipio' => 32],
            ['Id_distrito' => 157, 'Distrito' => 'Ilobasco', 'Id_municipio' => 31],
            ['Id_distrito' => 158, 'Distrito' => 'Jutiapa', 'Id_municipio' => 31],
            ['Id_distrito' => 159, 'Distrito' => 'San Isidro', 'Id_municipio' => 32],
            ['Id_distrito' => 160, 'Distrito' => 'Sensuntepeque', 'Id_municipio' => 32],
            ['Id_distrito' => 161, 'Distrito' => 'Tejutepeque', 'Id_municipio' => 31],
            ['Id_distrito' => 162, 'Distrito' => 'Victoria', 'Id_municipio' => 32],
            ['Id_distrito' => 163, 'Distrito' => 'Apastepeque', 'Id_municipio' => 33],
            ['Id_distrito' => 164, 'Distrito' => 'Guadalupe', 'Id_municipio' => 34],
            ['Id_distrito' => 165, 'Distrito' => 'San Cayetano Istepeque', 'Id_municipio' => 34],
            ['Id_distrito' => 166, 'Distrito' => 'San Esteban Catarina', 'Id_municipio' => 33],
            ['Id_distrito' => 167, 'Distrito' => 'San Ildefonso', 'Id_municipio' => 33],
            ['Id_distrito' => 168, 'Distrito' => 'San Lorenzo', 'Id_municipio' => 33],
            ['Id_distrito' => 169, 'Distrito' => 'San Sebastián', 'Id_municipio' => 33],
            ['Id_distrito' => 170, 'Distrito' => 'San Vicente', 'Id_municipio' => 34],
            ['Id_distrito' => 171, 'Distrito' => 'Santa Clara', 'Id_municipio' => 33],
            ['Id_distrito' => 172, 'Distrito' => 'Santo Domingo', 'Id_municipio' => 33],
            ['Id_distrito' => 173, 'Distrito' => 'Tecoluca', 'Id_municipio' => 34],
            ['Id_distrito' => 174, 'Distrito' => 'Tepetitán', 'Id_municipio' => 34],
            ['Id_distrito' => 175, 'Distrito' => 'Verapaz', 'Id_municipio' => 34],
            ['Id_distrito' => 176, 'Distrito' => 'Alegría', 'Id_municipio' => 35],
            ['Id_distrito' => 177, 'Distrito' => 'Berlín', 'Id_municipio' => 35],
            ['Id_distrito' => 178, 'Distrito' => 'California', 'Id_municipio' => 36],
            ['Id_distrito' => 179, 'Distrito' => 'Concepción Batres', 'Id_municipio' => 36],
            ['Id_distrito' => 180, 'Distrito' => 'El Triunfo', 'Id_municipio' => 35],
            ['Id_distrito' => 181, 'Distrito' => 'Ereguayquín', 'Id_municipio' => 36],
            ['Id_distrito' => 182, 'Distrito' => 'Estanzuelas', 'Id_municipio' => 35],
            ['Id_distrito' => 183, 'Distrito' => 'Jiquilisco', 'Id_municipio' => 37],
            ['Id_distrito' => 184, 'Distrito' => 'Jucuapa', 'Id_municipio' => 35],
            ['Id_distrito' => 185, 'Distrito' => 'Jucuarán', 'Id_municipio' => 36],
            ['Id_distrito' => 186, 'Distrito' => 'Mercedes Umaña', 'Id_municipio' => 35],
            ['Id_distrito' => 187, 'Distrito' => 'Nueva Granada', 'Id_municipio' => 35],
            ['Id_distrito' => 188, 'Distrito' => 'Ozatlán', 'Id_municipio' => 36],
            ['Id_distrito' => 189, 'Distrito' => 'Puerto El Triunfo', 'Id_municipio' => 37],
            ['Id_distrito' => 190, 'Distrito' => 'San Agustín', 'Id_municipio' => 37],
            ['Id_distrito' => 191, 'Distrito' => 'San Buenaventura', 'Id_municipio' => 35],
            ['Id_distrito' => 192, 'Distrito' => 'San Dionisio', 'Id_municipio' => 36],
            ['Id_distrito' => 193, 'Distrito' => 'San Francisco Javier', 'Id_municipio' => 37],
            ['Id_distrito' => 194, 'Distrito' => 'Santa Elena', 'Id_municipio' => 36],
            ['Id_distrito' => 195, 'Distrito' => 'Santa María', 'Id_municipio' => 36],
            ['Id_distrito' => 196, 'Distrito' => 'Santiago de María', 'Id_municipio' => 35],
            ['Id_distrito' => 197, 'Distrito' => 'Tecapán', 'Id_municipio' => 36],
            ['Id_distrito' => 198, 'Distrito' => 'Usulután', 'Id_municipio' => 36],
            ['Id_distrito' => 199, 'Distrito' => 'Carolina', 'Id_municipio' => 38],
            ['Id_distrito' => 200, 'Distrito' => 'Chapeltique', 'Id_municipio' => 38],
            ['Id_distrito' => 201, 'Distrito' => 'Chinameca', 'Id_municipio' => 40],
            ['Id_distrito' => 202, 'Distrito' => 'Chirilagua', 'Id_municipio' => 39],
            ['Id_distrito' => 203, 'Distrito' => 'Ciudad Barrios', 'Id_municipio' => 38],
            ['Id_distrito' => 204, 'Distrito' => 'Comacarán', 'Id_municipio' => 39],
            ['Id_distrito' => 205, 'Distrito' => 'El Tránsito', 'Id_municipio' => 40],
            ['Id_distrito' => 206, 'Distrito' => 'Lolotique', 'Id_municipio' => 40],
            ['Id_distrito' => 207, 'Distrito' => 'Moncagua', 'Id_municipio' => 39],
            ['Id_distrito' => 208, 'Distrito' => 'Nueva Guadalupe', 'Id_municipio' => 40],
            ['Id_distrito' => 209, 'Distrito' => 'Nuevo Edén de San Juan', 'Id_municipio' => 38],
            ['Id_distrito' => 210, 'Distrito' => 'Quelepa', 'Id_municipio' => 39],
            ['Id_distrito' => 211, 'Distrito' => 'San Antonio del Mosco', 'Id_municipio' => 38],
            ['Id_distrito' => 212, 'Distrito' => 'San Gerardo', 'Id_municipio' => 38],
            ['Id_distrito' => 213, 'Distrito' => 'San Jorge', 'Id_municipio' => 40],
            ['Id_distrito' => 214, 'Distrito' => 'San Luis de la Reina', 'Id_municipio' => 38],
            ['Id_distrito' => 215, 'Distrito' => 'San Miguel', 'Id_municipio' => 39],
            ['Id_distrito' => 216, 'Distrito' => 'San Rafael Oriente', 'Id_municipio' => 40],
            ['Id_distrito' => 217, 'Distrito' => 'Sesori', 'Id_municipio' => 38],
            ['Id_distrito' => 218, 'Distrito' => 'Uluazapa', 'Id_municipio' => 39],
            ['Id_distrito' => 219, 'Distrito' => 'Arambala', 'Id_municipio' => 41],
            ['Id_distrito' => 220, 'Distrito' => 'Cacaopera', 'Id_municipio' => 41],
            ['Id_distrito' => 221, 'Distrito' => 'Chilanga', 'Id_municipio' => 42],
            ['Id_distrito' => 222, 'Distrito' => 'Corinto', 'Id_municipio' => 41],
            ['Id_distrito' => 223, 'Distrito' => 'Delicias de Concepción', 'Id_municipio' => 42],
            ['Id_distrito' => 224, 'Distrito' => 'El Divisadero', 'Id_municipio' => 42],
            ['Id_distrito' => 225, 'Distrito' => 'El Rosario', 'Id_municipio' => 41],
            ['Id_distrito' => 226, 'Distrito' => 'Gualococti', 'Id_municipio' => 42],
            ['Id_distrito' => 227, 'Distrito' => 'Guatajiagua', 'Id_municipio' => 42],
            ['Id_distrito' => 228, 'Distrito' => 'Joateca', 'Id_municipio' => 41],
            ['Id_distrito' => 229, 'Distrito' => 'Jocoaitique', 'Id_municipio' => 41],
            ['Id_distrito' => 230, 'Distrito' => 'Jocoro', 'Id_municipio' => 42],
            ['Id_distrito' => 231, 'Distrito' => 'Lolotiquillo', 'Id_municipio' => 42],
            ['Id_distrito' => 232, 'Distrito' => 'Meanguera', 'Id_municipio' => 41],
            ['Id_distrito' => 233, 'Distrito' => 'Osicala', 'Id_municipio' => 42],
            ['Id_distrito' => 234, 'Distrito' => 'Perquín', 'Id_municipio' => 41],
            ['Id_distrito' => 235, 'Distrito' => 'San Carlos', 'Id_municipio' => 42],
            ['Id_distrito' => 236, 'Distrito' => 'San Fernando', 'Id_municipio' => 41],
            ['Id_distrito' => 237, 'Distrito' => 'San Francisco Gotera', 'Id_municipio' => 42],
            ['Id_distrito' => 238, 'Distrito' => 'San Isidro', 'Id_municipio' => 41],
            ['Id_distrito' => 239, 'Distrito' => 'San Simón', 'Id_municipio' => 42],
            ['Id_distrito' => 240, 'Distrito' => 'Sensembra', 'Id_municipio' => 42],
            ['Id_distrito' => 241, 'Distrito' => 'Sociedad', 'Id_municipio' => 42],
            ['Id_distrito' => 242, 'Distrito' => 'Torola', 'Id_municipio' => 41],
            ['Id_distrito' => 243, 'Distrito' => 'Yamabal', 'Id_municipio' => 42],
            ['Id_distrito' => 244, 'Distrito' => 'Yoloaiquín', 'Id_municipio' => 42],
            ['Id_distrito' => 245, 'Distrito' => 'Anamorós', 'Id_municipio' => 43],
            ['Id_distrito' => 246, 'Distrito' => 'Bolívar', 'Id_municipio' => 43],
            ['Id_distrito' => 247, 'Distrito' => 'Concepción de Oriente', 'Id_municipio' => 43],
            ['Id_distrito' => 248, 'Distrito' => 'Conchagua', 'Id_municipio' => 44],
            ['Id_distrito' => 249, 'Distrito' => 'El Carmen', 'Id_municipio' => 44],
            ['Id_distrito' => 250, 'Distrito' => 'El Sauce', 'Id_municipio' => 43],
            ['Id_distrito' => 251, 'Distrito' => 'Intipucá', 'Id_municipio' => 44],
            ['Id_distrito' => 252, 'Distrito' => 'La Unión', 'Id_municipio' => 44],
            ['Id_distrito' => 253, 'Distrito' => 'Lislique', 'Id_municipio' => 43],
            ['Id_distrito' => 254, 'Distrito' => 'Meanguera del Golfo', 'Id_municipio' => 44],
            ['Id_distrito' => 255, 'Distrito' => 'Nueva Esparta', 'Id_municipio' => 43],
            ['Id_distrito' => 256, 'Distrito' => 'Pasaquina', 'Id_municipio' => 43],
            ['Id_distrito' => 257, 'Distrito' => 'Polorós', 'Id_municipio' => 43],
            ['Id_distrito' => 258, 'Distrito' => 'San Alejo', 'Id_municipio' => 44],
            ['Id_distrito' => 259, 'Distrito' => 'San José La Fuente', 'Id_municipio' => 43],
            ['Id_distrito' => 260, 'Distrito' => 'Santa Rosa de Lima', 'Id_municipio' => 43],
            ['Id_distrito' => 261, 'Distrito' => 'Yayantique', 'Id_municipio' => 44],
            ['Id_distrito' => 262, 'Distrito' => 'Yucuaiquín', 'Id_municipio' => 44],

            // Honduras
            ['Id_distrito' => 9, 'Distrito' => 'Tegucigalpa', 'Id_municipio' => 13],
            ['Id_distrito' => 10, 'Distrito' => 'Comayagüela', 'Id_municipio' => 13],
            ['Id_distrito' => 11, 'Distrito' => 'San Pedro Sula Centro', 'Id_municipio' => 14],
            ['Id_distrito' => 12, 'Distrito' => 'Choloma Centro', 'Id_municipio' => 15],
            ['Id_distrito' => 13, 'Distrito' => 'La Ceiba Centro', 'Id_municipio' => 16],
        ];
    }

    public function run(): void
    {
        $isPostgre = strcasecmp((string) ($this->db->DBDriver ?? ''), 'Postgre') === 0;
        $tables    = $this->db->listTables();

        $resolveTable = static function (string $canonical) use ($isPostgre, $tables): string {
            if ($isPostgre && ! in_array($canonical, $tables, true) && in_array(strtolower($canonical), $tables, true)) {
                return strtolower($canonical);
            }
            return $canonical;
        };

        // 0. Resolve actual country IDs by name from Pais/pais table
        $paisTable = $resolveTable('Pais');
        $paisRows  = $this->db->table($paisTable)->get()->getResultArray();
        $countryIdMap = []; // canonical id (1=SV, 2=GT, 3=HN) => actual DB id_pais
        foreach ($paisRows as $pRow) {
            $pId   = (int) ($pRow['id_pais'] ?? $pRow['Id_pais'] ?? 0);
            $pName = mb_strtolower(trim((string) ($pRow['pais'] ?? $pRow['Pais'] ?? '')), 'UTF-8');
            if ($pName === 'el salvador') {
                $countryIdMap[1] = $pId;
            } elseif ($pName === 'guatemala') {
                $countryIdMap[2] = $pId;
            } elseif ($pName === 'honduras') {
                $countryIdMap[3] = $pId;
            }
        }

        $departamentos = [];
        $validDeptoIds = [];
        foreach (self::getDepartamentos() as $depto) {
            $canonicalPaisId = $depto['Id_pais'];
            if (! isset($countryIdMap[$canonicalPaisId])) {
                continue;
            }
            $depto['Id_pais'] = $countryIdMap[$canonicalPaisId];
            $departamentos[]  = $depto;
            $validDeptoIds[$depto['Id_departamento']] = true;
        }

        $municipios   = [];
        $validMuniIds = [];
        foreach (self::getMunicipios() as $muni) {
            if (! isset($validDeptoIds[$muni['Id_departamento']])) {
                continue;
            }
            $municipios[] = $muni;
            $validMuniIds[$muni['Id_municipio']] = true;
        }

        $distritos = [];
        foreach (self::getDistritos() as $dist) {
            if (! isset($validMuniIds[$dist['Id_municipio']])) {
                continue;
            }
            $distritos[] = $dist;
        }

        // 1. Departamentos
        $deptoTable = $resolveTable('Departamento');
        $deptoLower = ($deptoTable === 'departamento');
        $deptoPk    = $deptoLower ? 'id_departamento' : 'Id_departamento';
        $this->upsertRows($deptoTable, $deptoPk, $departamentos, $deptoLower);
        $this->syncPostgreSequence($deptoTable, $deptoPk);

        // 2. Municipios
        $muniTable = $resolveTable('Municipio');
        $muniLower = ($muniTable === 'municipio');
        $muniPk    = $muniLower ? 'id_municipio' : 'Id_municipio';
        $this->upsertRows($muniTable, $muniPk, $municipios, $muniLower);
        $this->syncPostgreSequence($muniTable, $muniPk);

        // 3. Distritos
        $distTable = $resolveTable('Distrito');
        $distLower = ($distTable === 'distrito');
        $distPk    = $distLower ? 'id_distrito' : 'Id_distrito';
        $this->upsertRows($distTable, $distPk, $distritos, $distLower);
        $this->syncPostgreSequence($distTable, $distPk);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function upsertRows(string $table, string $pkCol, array $rows, bool $useLowerKeys): void
    {
        foreach ($rows as $row) {
            $payload  = $useLowerKeys ? array_change_key_case($row, CASE_LOWER) : $row;
            $existing = $this->db->table($table)->where($pkCol, $payload[$pkCol])->get()->getRow();

            if ($existing) {
                $updateData = $payload;
                unset($updateData[$pkCol]);
                $this->db->table($table)->where($pkCol, $payload[$pkCol])->update($updateData);
            } else {
                $this->db->table($table)->insert($payload);
            }
        }
    }

    private function syncPostgreSequence(string $table, string $pkColumn): void
    {
        if (strcasecmp((string) ($this->db->DBDriver ?? ''), 'Postgre') !== 0) {
            return;
        }

        $pgTableIdent = ($table === strtolower($table)) ? $table : '"' . $table . '"';
        $row          = $this->db->query('SELECT pg_get_serial_sequence(?, ?) AS seq', [$pgTableIdent, $pkColumn])->getRow();

        if ($row && ! empty($row->seq)) {
            $quotedTable = $this->db->escapeIdentifiers($table);
            $quotedPk    = $this->db->escapeIdentifiers($pkColumn);
            $this->db->query(
                'SELECT setval(?, GREATEST(COALESCE((SELECT MAX(' . $quotedPk . ') FROM ' . $quotedTable . '), 0), 1), (SELECT COUNT(*) > 0 FROM ' . $quotedTable . '))',
                [$row->seq]
            );
        }
    }
}
