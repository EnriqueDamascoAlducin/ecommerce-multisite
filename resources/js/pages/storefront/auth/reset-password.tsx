import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function CustomerResetPassword({ token, email }: { token: string; email: string }) {
    return (
        <div className="mx-auto max-w-sm py-8">
            <Head title="Restablecer contraseña" />
            <h1 className="mb-6 text-2xl font-semibold">Restablecer contraseña</h1>

            <Form action="/cuenta/restablecer" method="post" className="space-y-4">
                {({ processing, errors }) => (
                    <>
                        <input type="hidden" name="token" value={token} />
                        <div className="grid gap-2">
                            <Label htmlFor="email">Correo</Label>
                            <Input id="email" name="email" type="email" defaultValue={email} required />
                            <InputError message={errors.email} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="password">Nueva contraseña</Label>
                            <Input id="password" name="password" type="password" required autoFocus />
                            <InputError message={errors.password} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">Confirmar contraseña</Label>
                            <Input id="password_confirmation" name="password_confirmation" type="password" required />
                        </div>
                        <Button className="w-full" disabled={processing}>Restablecer</Button>
                    </>
                )}
            </Form>
        </div>
    );
}
