import TopicCard from '../components/TopicCard';
import Formula from '../components/Formula';

const Areas = () => {
  return (
    <div className="space-y-6 md:space-y-8">
      <h1 className="text-3xl md:text-4xl lg:text-5xl font-bold text-gray-900">
        Площади фигур
      </h1>

      <TopicCard title="Основные формулы площадей">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
          <div className="p-4 md:p-6 bg-blue-50 rounded-lg">
            <h4 className="font-semibold mb-3 text-sm md:text-base">Прямоугольник</h4>
            <Formula latex="S = a \\cdot b" display={true} />
          </div>

          <div className="p-4 md:p-6 bg-green-50 rounded-lg">
            <h4 className="font-semibold mb-3 text-sm md:text-base">Квадрат</h4>
            <Formula latex="S = a^2" display={true} />
          </div>

          <div className="p-4 md:p-6 bg-purple-50 rounded-lg">
            <h4 className="font-semibold mb-3 text-sm md:text-base">Треугольник</h4>
            <Formula latex="S = \\frac{1}{2} \\cdot a \\cdot h" display={true} />
          </div>

          <div className="p-4 md:p-6 bg-yellow-50 rounded-lg">
            <h4 className="font-semibold mb-3 text-sm md:text-base">Параллелограмм</h4>
            <Formula latex="S = a \\cdot h" display={true} />
          </div>

          <div className="p-4 md:p-6 bg-red-50 rounded-lg">
            <h4 className="font-semibold mb-3 text-sm md:text-base">Трапеция</h4>
            <Formula latex="S = \\frac{a + b}{2} \\cdot h" display={true} />
          </div>

          <div className="p-4 md:p-6 bg-indigo-50 rounded-lg">
            <h4 className="font-semibold mb-3 text-sm md:text-base">Круг</h4>
            <Formula latex="S = \\pi r^2" display={true} />
          </div>
        </div>
      </TopicCard>
    </div>
  );
};

export default Areas;
