import TopicCard from '../components/TopicCard';
import Formula from '../components/Formula';
import Quiz from '../components/Quiz';

const Circles = () => {
  return (
    <div className="space-y-6 md:space-y-8">
      <h1 className="text-3xl md:text-4xl lg:text-5xl font-bold text-gray-900">
        Окружность
      </h1>

      <TopicCard
        title="Определение"
        description="Окружность — это множество точек плоскости, равноудалённых от данной точки (центра)."
      />

      <TopicCard title="Основные элементы">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div className="p-4 bg-blue-50 rounded-lg">
            <h4 className="font-semibold mb-2 text-sm md:text-base">Радиус (r)</h4>
            <p className="text-xs md:text-sm text-gray-700">
              Отрезок, соединяющий центр с любой точкой окружности
            </p>
          </div>
          <div className="p-4 bg-green-50 rounded-lg">
            <h4 className="font-semibold mb-2 text-sm md:text-base">Диаметр (d)</h4>
            <p className="text-xs md:text-sm text-gray-700">
              Отрезок, проходящий через центр и соединяющий две точки окружности: <Formula latex="d = 2r" />
            </p>
          </div>
        </div>
      </TopicCard>

      <TopicCard title="Длина окружности">
        <div className="bg-blue-50 p-4 md:p-6 rounded-lg">
          <Formula latex="C = 2\\pi r = \\pi d" display={true} />
        </div>
      </TopicCard>

      <TopicCard title="Площадь круга">
        <div className="bg-green-50 p-4 md:p-6 rounded-lg">
          <Formula latex="S = \\pi r^2" display={true} />
        </div>
      </TopicCard>

      <Quiz
        question="Найдите площадь круга с радиусом 5 (используйте π ≈ 3.14)."
        correctAnswer={78.5}
        hint="S = πr² = 3.14 × 5²"
      />
    </div>
  );
};

export default Circles;
