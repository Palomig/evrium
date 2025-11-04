import TopicCard from '../components/TopicCard';
import Formula from '../components/Formula';

const Quadrilaterals = () => {
  return (
    <div className="space-y-6 md:space-y-8">
      <h1 className="text-3xl md:text-4xl lg:text-5xl font-bold text-gray-900">
        Четырёхугольники
      </h1>

      <TopicCard
        title="Параллелограмм"
        description="Четырёхугольник, у которого противоположные стороны попарно параллельны"
      >
        <div className="bg-blue-50 p-4 md:p-6 rounded-lg">
          <h4 className="font-semibold mb-3 text-sm md:text-base">Свойства:</h4>
          <ul className="space-y-2 text-xs md:text-sm text-gray-700">
            <li>• Противоположные стороны равны</li>
            <li>• Противоположные углы равны</li>
            <li>• Диагонали точкой пересечения делятся пополам</li>
          </ul>
        </div>
      </TopicCard>

      <TopicCard title="Прямоугольник">
        <p className="text-sm md:text-base text-gray-700 mb-4">
          Параллелограмм, у которого все углы прямые.
        </p>
        <div className="bg-green-50 p-4 md:p-6 rounded-lg">
          <Formula latex="S = a \\cdot b" display={true} />
          <p className="text-xs md:text-sm text-gray-600 mt-2">
            Площадь прямоугольника
          </p>
        </div>
      </TopicCard>

      <TopicCard title="Ромб">
        <p className="text-sm md:text-base text-gray-700 mb-4">
          Параллелограмм, у которого все стороны равны.
        </p>
        <div className="bg-purple-50 p-4 md:p-6 rounded-lg">
          <Formula latex="S = \\frac{1}{2} \\cdot d_1 \\cdot d_2" display={true} />
          <p className="text-xs md:text-sm text-gray-600 mt-2">
            где d₁ и d₂ — диагонали ромба
          </p>
        </div>
      </TopicCard>

      <TopicCard title="Квадрат">
        <p className="text-sm md:text-base text-gray-700 mb-4">
          Прямоугольник, у которого все стороны равны.
        </p>
        <div className="bg-yellow-50 p-4 md:p-6 rounded-lg">
          <Formula latex="S = a^2" display={true} />
          <p className="text-xs md:text-sm text-gray-600 mt-2">
            где a — сторона квадрата
          </p>
        </div>
      </TopicCard>

      <TopicCard title="Трапеция">
        <p className="text-sm md:text-base text-gray-700 mb-4">
          Четырёхугольник, у которого две стороны параллельны (основания), а две другие — нет.
        </p>
        <div className="bg-red-50 p-4 md:p-6 rounded-lg">
          <Formula latex="S = \\frac{a + b}{2} \\cdot h" display={true} />
          <p className="text-xs md:text-sm text-gray-600 mt-2">
            где a и b — основания, h — высота
          </p>
        </div>
      </TopicCard>
    </div>
  );
};

export default Quadrilaterals;
