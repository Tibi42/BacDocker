<?php

namespace App\Controller;

use App\Enum\ActivityKind;
use App\Repository\ActivityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Contrôleur de la page d'accueil publique.
 *
 * Affiche le calendrier des activités du mois, le carrousel hero et les
 * modales de connexion/inscription. La navigation par mois/année et le
 * filtrage par type d'activité se font via les paramètres GET.
 *
 * Les requêtes Turbo Frame du calendrier renvoient uniquement les frames
 * (sans hero / articles / modales) pour accélérer la navigation.
 */
class HomeController extends AbstractController
{
    private const CALENDAR_TURBO_FRAMES = ['calendar-frame', 'calendar-desktop-frame'];

    public function __construct(
        private readonly ActivityRepository $activityRepository,
        private readonly CsrfTokenManagerInterface $csrfTokenManager
    ) {
    }

    /**
     * Page d'accueil : calendrier + modales de connexion.
     *
     * Paramètres GET acceptés :
     *   - month  (int)    : mois à afficher (1-12, défaut : mois courant)
     *   - year   (int)    : année à afficher (2020-2100, défaut : année courante)
     *   - day    (int)    : jour sélectionné pour voir le détail des activités
     *   - type   (string) : filtre par type d'activité (valeurs de ActivityKind)
     *   - open   (string) : "login" pour ouvrir la modale de connexion automatiquement
     */
    #[Route('/', name: 'app_home')]
    public function index(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        $calendar = $this->buildCalendarViewData($request);

        $turboFrame = $request->headers->get('Turbo-Frame');
        if (\in_array($turboFrame, self::CALENDAR_TURBO_FRAMES, true)) {
            return $this->render('home/_calendar_frames.html.twig', $calendar);
        }

        return $this->render('home/index.html.twig', $calendar + [
            'login_csrf_token' => $this->csrfTokenManager->getToken('authenticate')->getValue(),
            'login_error' => $authenticationUtils->getLastAuthenticationError(),
            'last_username' => $authenticationUtils->getLastUsername(),
        ]);
    }

    /**
     * Construit les variables Twig du calendrier (mois, filtres, activités).
     *
     * @return array<string, mixed>
     */
    private function buildCalendarViewData(Request $request): array
    {
        $now = new \DateTimeImmutable();
        $currentMonth = (int) $now->format('n');
        $currentYear = (int) $now->format('Y');
        $today = (int) $now->format('j');

        $month = max(1, min(12, (int) $request->query->get('month', $currentMonth)));
        $year = max(2020, min(2100, (int) $request->query->get('year', $currentYear)));

        $allowedTypes = ActivityKind::values();
        $filterType = $request->query->get('type');
        if ($filterType !== null && !\in_array($filterType, $allowedTypes, true)) {
            $filterType = null;
        }

        $selectedDay = (int) $request->query->get('day', 0);

        $firstDay = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month));
        $lastDay = $firstDay->modify('last day of this month')->setTime(23, 59, 59);
        $lastDayNum = (int) $lastDay->format('j');

        $activities = $this->activityRepository->findBetween($firstDay, $lastDay, $filterType);

        $activitiesForSelectedDay = [];
        if ($selectedDay >= 1 && $selectedDay <= $lastDayNum) {
            foreach ($activities as $activity) {
                if ((int) $activity->getStartAt()->format('j') === $selectedDay && $activity->getStartAt() > $now) {
                    $activitiesForSelectedDay[] = $activity;
                }
            }
        } else {
            $selectedDay = 0;
        }

        $futureActivities = [];
        $daysWithActivities = [];
        $activitiesCountByDay = [];
        $activitiesTypesByDay = [];

        foreach ($activities as $activity) {
            $startAt = $activity->getStartAt();
            $d = (int) $startAt->format('j');

            $daysWithActivities[$d] = $d;
            $activitiesCountByDay[$d] = ($activitiesCountByDay[$d] ?? 0) + 1;

            $type = $activity->getType() ?? '';
            if ($type !== '') {
                if (!isset($activitiesTypesByDay[$d])) {
                    $activitiesTypesByDay[$d] = $type;
                } elseif ($activitiesTypesByDay[$d] !== $type) {
                    $activitiesTypesByDay[$d] = 'mixed';
                }
            }

            if ($startAt > $now) {
                $futureActivities[] = $activity;
            }
        }

        $dayOfWeek = (int) $firstDay->format('N');
        $calendarDays = array_fill(0, $dayOfWeek - 1, null);
        for ($d = 1; $d <= $lastDayNum; ++$d) {
            $calendarDays[] = $d;
        }

        $prev = $firstDay->modify('-1 month');
        $next = $firstDay->modify('+1 month');

        return [
            'nowYear' => $currentYear,
            'nowMonth' => $currentMonth,
            'nowDay' => $today,
            'today' => ($month === $currentMonth && $year === $currentYear) ? $today : 0,
            'calendarMonth' => $month,
            'calendarYear' => $year,
            'calendarMonthName' => $this->getMonthName($month),
            'calendarDays' => $calendarDays,
            'daysWithActivities' => array_values($daysWithActivities),
            'activitiesCountByDay' => $activitiesCountByDay,
            'activitiesTypesByDay' => $activitiesTypesByDay,
            'filterType' => $filterType,
            'activities' => $futureActivities,
            'selectedDay' => $selectedDay,
            'activitiesForSelectedDay' => $activitiesForSelectedDay,
            'prevMonth' => (int) $prev->format('n'),
            'prevYear' => (int) $prev->format('Y'),
            'prevMonthName' => $this->getMonthName((int) $prev->format('n')),
            'nextMonth' => (int) $next->format('n'),
            'nextYear' => (int) $next->format('Y'),
            'nextMonthName' => $this->getMonthName((int) $next->format('n')),
        ];
    }

    /**
     * Retourne le nom du mois en français (1 = Janvier … 12 = Décembre).
     */
    private function getMonthName(int $month): string
    {
        $months = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ];

        return $months[$month] ?? '';
    }
}
