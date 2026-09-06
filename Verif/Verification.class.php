<?php
require_once(dirname(__FILE__, 3) . '/config.php');
require_once('Common/Fun_Various.inc.php');

class Verification
{
    private $TourId;
    private $anomalies;
    private $doublons;
    private $obligatoires;
    private $cibles;
    private $ciblesDupliquees;

    public function __construct()
    {
        $this->TourId = $_SESSION['TourId'];
    }

    // Requête pour trouver les anomalies
    // On vérifie TOUS les archers (inscrits 1 ou plusieurs fois)
    public function getAnomalies()
    {
        if ($this->anomalies){
            return $this->anomalies;
        }

        $query = "
            SELECT
                e.EnId,
                e.EnCode AS Licence,
                e.EnFirstName AS Prenom,
                e.EnName AS Nom,
                c.CoCode AS Pays,
                e.EnDivision AS Division,
                e.EnClass AS Classe,
                e.EnAgeClass AS AgeClasse,
                q.QuSession AS Depart,
                e.EnIndFEvent AS FinaleInd,
                (
                    SELECT MIN(q2.QuSession)
                    FROM Entries e2
                    INNER JOIN Qualifications q2 ON e2.EnId = q2.QuId
                    WHERE e2.EnCode = e.EnCode
                    AND e2.EnTournament = e.EnTournament
                    AND e2.EnCode != ''
                    AND e2.EnDivision = e.EnDivision
                    AND e2.EnClass = e.EnClass
                ) AS PremierDepartDivClasse,
                (
                    SELECT COUNT(*)
                    FROM Entries e3
                    WHERE e3.EnCode = e.EnCode
                    AND e3.EnTournament = e.EnTournament
                    AND e3.EnCode != ''
                    AND e3.EnDivision = e.EnDivision
                    AND e3.EnClass = e.EnClass
                ) AS NbInscriptionsDivClasse,
                CASE
                    WHEN q.QuSession = (
                        SELECT MIN(q2.QuSession)
                        FROM Entries e2
                        INNER JOIN Qualifications q2 ON e2.EnId = q2.QuId
                        WHERE e2.EnCode = e.EnCode
                        AND e2.EnTournament = e.EnTournament
                        AND e2.EnCode != ''
                        AND e2.EnDivision = e.EnDivision
                        AND e2.EnClass = e.EnClass
                    ) AND e.EnIndFEvent = 0 THEN 'Premier départ pour cette (Division, Classe) : devrait être OUI'
                    WHEN q.QuSession > (
                        SELECT MIN(q2.QuSession)
                        FROM Entries e2
                        INNER JOIN Qualifications q2 ON e2.EnId = q2.QuId
                        WHERE e2.EnCode = e.EnCode
                        AND e2.EnTournament = e.EnTournament
                        AND e2.EnCode != ''
                        AND e2.EnDivision = e.EnDivision
                        AND e2.EnClass = e.EnClass
                    ) AND e.EnIndFEvent = 1 THEN 'Départ supplémentaire dans cette (Division, Classe) : devrait être NON'
                    ELSE NULL
                END AS Probleme
            FROM Entries e
            INNER JOIN Qualifications q ON e.EnId = q.QuId
            LEFT JOIN Countries c ON e.EnCountry = c.CoId AND e.EnTournament = c.CoTournament
            WHERE e.EnTournament = $this->TourId
            AND e.EnCode != ''
            AND (
                (q.QuSession = (
                    SELECT MIN(q2.QuSession)
                    FROM Entries e2
                    INNER JOIN Qualifications q2 ON e2.EnId = q2.QuId
                    WHERE e2.EnCode = e.EnCode
                    AND e2.EnTournament = e.EnTournament
                    AND e2.EnCode != ''
                    AND e2.EnDivision = e.EnDivision
                    AND e2.EnClass = e.EnClass
                ) AND e.EnIndFEvent = 0)
                OR
                (q.QuSession > (
                    SELECT MIN(q2.QuSession)
                    FROM Entries e2
                    INNER JOIN Qualifications q2 ON e2.EnId = q2.QuId
                    WHERE e2.EnCode = e.EnCode
                    AND e2.EnTournament = e.EnTournament
                    AND e2.EnCode != ''
                    AND e2.EnDivision = e.EnDivision
                    AND e2.EnClass = e.EnClass
                ) AND e.EnIndFEvent = 1)
            )
            ORDER BY e.EnCode, e.EnDivision, e.EnClass, q.QuSession
        ";

        $result = safe_r_sql($query);
        $this->anomalies = $result;

        return $result;
    }

    public function getNbAnomalies()
    {
        return safe_num_rows($this->getAnomalies());
    }

    // Doublons dans un même départ
    public function getDoublons()
    {
        if ($this->doublons){
            return $this->doublons;
        }

        $query = "
            SELECT
                e.EnCode AS Licence,
                e.EnFirstName AS Prenom,
                e.EnName AS Nom,
                q.QuSession AS Depart,
                COUNT(*) AS NbDoublons
            FROM Entries e
            INNER JOIN Qualifications q ON e.EnId = q.QuId
            WHERE e.EnTournament = $this->TourId
            AND e.EnCode != ''
            GROUP BY e.EnCode, e.EnFirstName, e.EnName, q.QuSession
            HAVING COUNT(*) > 1
            ORDER BY NbDoublons DESC, q.QuSession, e.EnCode
        ";

        $result = safe_r_sql($query);
        $this->doublons = $result;

        return $result;
    }

    public function getNbDoublons()
    {
        return safe_num_rows($this->getDoublons());
    }

    // Vérification des champs obligatoires (Division, AgeClasse, Classe)
    public function getObligatoires()
    {
        if ($this->obligatoires){
            return $this->obligatoires;
        }

        $query = "
            SELECT
                e.EnId,
                e.EnCode AS Licence,
                e.EnFirstName AS Prenom,
                e.EnName AS Nom,
                c.CoCode AS Pays,
                e.EnDivision AS Division,
                e.EnClass AS Classe,
                e.EnAgeClass AS AgeClasse,
                q.QuSession AS Depart,
                CASE
                    WHEN e.EnDivision = '' OR e.EnDivision IS NULL THEN 'Division manquante'
                    WHEN e.EnAgeClass = '' OR e.EnAgeClass IS NULL THEN 'Age Cl. manquant'
                    WHEN e.EnClass = '' OR e.EnClass IS NULL THEN 'Classe manquante'
                    ELSE 'OK'
                END AS Probleme,
                CASE
                    WHEN e.EnDivision = '' OR e.EnDivision IS NULL THEN 'division'
                    WHEN e.EnAgeClass = '' OR e.EnAgeClass IS NULL THEN 'age_classe'
                    WHEN e.EnClass = '' OR e.EnClass IS NULL THEN 'classe'
                    ELSE 'ok'
                END AS ChampManquant
            FROM Entries e
            INNER JOIN Qualifications q ON e.EnId = q.QuId
            LEFT JOIN Countries c ON e.EnCountry = c.CoId AND e.EnTournament = c.CoTournament
            WHERE e.EnTournament = $this->TourId
            AND e.EnCode != ''
            AND (
                e.EnDivision = '' OR e.EnDivision IS NULL
                OR e.EnAgeClass = '' OR e.EnAgeClass IS NULL
                OR e.EnClass = '' OR e.EnClass IS NULL
            )
            ORDER BY e.EnCode, q.QuSession
    ";

        $result = safe_r_sql($query);
        $this->obligatoires = $result;

        return $result;
    }

    public function getNbObligatoires()
    {
        return safe_num_rows($this->getObligatoires());
    }

    // Vérification des archers sans cible assignée
    public function getCibles()
    {
        if ($this->cibles){
            return $this->cibles;
        }

        $query = "
            SELECT
                e.EnId,
                e.EnCode AS Licence,
                e.EnFirstName AS Prenom,
                e.EnName AS Nom,
                c.CoCode AS Pays,
                e.EnDivision AS Division,
                e.EnClass AS Classe,
                q.QuSession AS Depart,
                CASE
                    WHEN (q.QuTarget IS NULL OR q.QuTarget = 0 OR q.QuTarget = '') THEN 'Cible manquante'
                    WHEN (q.QuLetter IS NULL OR q.QuLetter = '') THEN 'Lettre manquante'
                    ELSE CONCAT(q.QuTarget, ' ', q.QuLetter)
                END AS CibleDetail,
                CASE
                    WHEN (q.QuTarget IS NULL OR q.QuTarget = 0 OR q.QuTarget = '') THEN 'NON ASSIGNÉ'
                    WHEN (q.QuLetter IS NULL OR q.QuLetter = '') THEN 'SANS LETTRE'
                    ELSE CONCAT(q.QuTarget, q.QuLetter)
                END AS Cible,
                CONCAT(IFNULL(q.QuTarget, ''), IFNULL(q.QuLetter, '')) AS CibleComplete
            FROM Entries e
            INNER JOIN Qualifications q ON e.EnId = q.QuId
            LEFT JOIN Countries c ON e.EnCountry = c.CoId AND e.EnTournament = c.CoTournament
            WHERE e.EnTournament = $this->TourId
            AND e.EnCode != ''
            AND (
                (q.QuTarget IS NULL OR q.QuTarget = 0 OR q.QuTarget = '')
                OR (q.QuLetter IS NULL OR q.QuLetter = '')
            )
            AND e.EnAthlete = 1
            ORDER BY e.EnCode, q.QuSession
        ";

        $result = safe_r_sql($query);
        $this->cibles = $result;

        return $result;
    }

    public function getNbCibles()
    {
        return safe_num_rows($this->getCibles());
    }

    // Vérification des cibles avec plusieurs archers dans un même départ
    public function getCiblesDupliquees()
    {
        if ($this->ciblesDupliquees){
            return $this->ciblesDupliquees;
        }

        $query = "
            SELECT
                CONCAT(q.QuTarget, ' ', q.QuLetter) AS Cible,
                q.QuSession AS Depart,
                COUNT(*) AS NbArchers,
                GROUP_CONCAT(
                    CONCAT(e.EnCode, ' - ', e.EnFirstName, ' ', e.EnName)
                    ORDER BY e.EnCode
                    SEPARATOR '<br>'
                ) AS Archers
            FROM Qualifications q
            INNER JOIN Entries e ON q.QuId = e.EnId
            WHERE e.EnTournament = $this->TourId
            AND e.EnCode != ''
            AND q.QuTarget IS NOT NULL
            AND q.QuTarget != 0
            AND q.QuTarget != ''
            AND q.QuLetter IS NOT NULL
            AND q.QuLetter != ''
            GROUP BY q.QuTarget, q.QuLetter, q.QuSession
            HAVING COUNT(*) > 1
            ORDER BY q.QuSession, q.QuTarget, q.QuLetter
        ";

        $result = safe_r_sql($query);
        $this->ciblesDupliquees = $result;

        return $result;
    }

    public function getNbCiblesDupliquees()
    {
        return safe_num_rows($this->getCiblesDupliquees());
    }

    public function partecipantsHaveErrors()
    {
        return $this->getNbAnomalies() || $this->getNbDoublons() || $this->getNbObligatoires() || $this->getNbCibles() || $this->getNbCiblesDupliquees();
    }
}
